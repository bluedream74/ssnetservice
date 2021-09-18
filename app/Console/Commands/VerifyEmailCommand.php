<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CompanyEmail;
use App\Models\Company;
use App\Models\NotificationLog;

class VerifyEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $emails = NotificationLog::where('status', 'Open')->pluck('email');
        // CompanyEmail::whereIn('email', $emails)->update(['is_verified' => 1]);
        
        // $this->info(sizeof($emails));
        // $emails = CompanyEmail::where('is_verified', 0)->orderBy('id')->limit(100)->get();
        // $service = new \App\Services\MailService();
        // foreach ($emails as $companyEmail) {
        //     try {
        //         list ($status, $res) = $service->lookup($companyEmail->email);

        //         if ($status && $res['status'] == 'valid') {
        //             $companyEmail->update(['is_verified' => 1]);
        //         } else {
        //             $companyEmail->delete();
        //         }
        //         sleep(1);
        //     } catch (\Throwable $e) {
        //         \Log::error($e->getMessage());
        //     }
        // }
        Company::whereDoesntHave('emails')->delete();

        $companies = Company::whereHas('emails', function($query) {
            return $query->where('is_verified', 0);
        })->orderBy('id')->limit(50)->get();
        $service = new \App\Services\MailService();
        foreach ($companies as $company) {
            foreach ($company->emails as $companyEmail) {
                try {
                    list ($status, $res) = $service->lookup($companyEmail->email);
    
                    if ($status && isset($res['status']) && $res['status'] == 'valid') {
                        $companyEmail->update(['is_verified' => 1]);
                        $company->emails()->where('is_verified', 0)->delete();
                        break;
                    } else {
                        $companyEmail->delete();
                    }
                    sleep(1);
                } catch (\Throwable $e) {
                    \Log::error($e->getMessage());
                }
            }
        }

        Company::whereDoesntHave('emails')->delete();
    }
}
