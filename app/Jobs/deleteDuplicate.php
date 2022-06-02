<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company;
use Illuminate\Support\Arr;
use App\Models\Source;
use App\Models\SubSource;

class deleteDuplicate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $attributes=[];
    public function __construct($attributes)
    {
        //
        $this->attributes = $attributes;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $query = Company::query();
        if (!empty($value = Arr::get($this->attributes, 'q'))) {
            $query->where(function ($query) use ($value) {
                $query->where('name', 'like', "%{$value}%")
                    ->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('url', 'like', "%{$value}%")
                    ->orWhere('area', 'like', "%{$value}%");
            });
        }
        
        if (!empty($value = Arr::get($this->attributes, 'source'))) {
            $value = Source::where('sort_no', $value)->first()->name;
            $query->where('source', $value);
        }

        if (!empty($value = Arr::get($this->attributes, 'subsource'))) {
            $query->where('subsource', $value);
        }

        if (!empty($value = Arr::get($this->attributes, 'area'))) {
            $query->whereIn('area', $value);
        }

        if (!empty($value = Arr::get($this->attributes, 'status'))) {
            $query->where('status', $value);
        }

        if (!empty($value = Arr::get($this->attributes, 'phone'))) {
            if (intval($value) === 1) {
                $query->whereHas('phones');
            } else {
                $query->whereDoesntHave('phones');
            }
        }
        
        if (!empty($value = Arr::get($this->attributes, 'origin'))) {
            if ($value==1) {
                $query->whereNotNull('contact_form_url');
            }
            if ($value==2) {
                $query->whereNull('contact_form_url');
            }
        }
        
        $urls = $query->whereNotNull('url')
                        ->selectRaw("SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(REPLACE(LOWER(url), 'https://', ''), 'http://', ''), 'www.', ''), '/', 1), '?', 1) as url")
                        ->distinct()
                        ->pluck('url');
        
        if (sizeof($urls) < $query->whereNotNull('url')->count()) {
            foreach ($urls as $url) {
                $parse = parse_url($url);
                try {
                    $host = $parse['path'];
                    if (!$host || !strlen($host)) {
                        continue;
                    }
                    $query = Company::query();
                    if ($query->where('url', 'LIKE', "%{$host}%")->count() > 1) {
                        $company = $query->where('url', 'LIKE', "%{$host}%")->latest()->first();
                        if (Company::where('subsource', $company->subsource)->count() == 1) {
                            SubSource::where('name', $company->subsource)->delete();
                        }
                        Company::where('url', 'LIKE', "%{$host}%")
                    ->where('id', '!=', $company->id)
                    ->delete();
                    }
                } catch (\Throwable $e) {
                    print_r($e->getMessage());
                    continue;
                }
            }
        }
    }
}
