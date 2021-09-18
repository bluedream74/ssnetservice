<?php

namespace App\Exports;

use App\Models\Company;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CompanyExport implements FromCollection, WithHeadings, WithMapping
{
    protected $params = array();

    /**
    * Optional headers
    */
    private $headers = [
        'Content-Type' => 'text/csv',
    ];

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function headings(): array
    {
        return ["カテゴリ", "会社名", "URL","お問い合わせフォームのURL", "エリア", "ステータス","電話番号"];
    }

    /**
    * @var Company $company
    */
    public function map($company): array
    {
        $res = array();
        $res[] = $company->source;
        $res[] = $company->name;
        $res[] = $company->url;
        $res[] = $company->contact_form_url;
        $res[] = $company->area;
        $res[] = $company->status;


        foreach ($company->phones as $phone) {
            $res[] = $phone->phone;
        }

        return $res;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Company::query();

        if (!empty($value = Arr::get($this->params, 'q'))) {
            $query->where(function ($query) use ($value) {
                $query->where('name', 'like', "%{$value}%")
                    ->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('url', 'like', "%{$value}%");
            });
        }

        if (!empty($value = Arr::get($this->params, 'source'))) {
            $query->where('source', $value);
        }

        if (!empty($value = Arr::get($this->params, 'status'))) {
            $query->where('status', $value);
        }

        if (!empty($value = Arr::get($this->params, 'phone'))) {
            if (intval($value) === 1) {
                $query->whereHas('phones');
            } else {
                $query->whereDoesntHave('phones');
            }
        }

        // if (!empty($value = Arr::get($this->params, 'origin'))) {
        //     $query->where('is_origin_email', $value);
        // }

        return $query->orderByDesc('source')->orderBy('name')->get();
    }
}
