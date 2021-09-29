<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSendEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:send:email';

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
        $company = \App\Models\Company::first();
        $contact = \App\Models\Contact::first();

        try {
            // \App\Jobs\SendMagazineEmailJob::dispatch("webexpert007@yahoo.co.jp", new \App\Notifications\MailMagazineNotification($contact, "webexpert007@yahoo.co.jp", $company->name), $company);
            Mail::to("webexpert007@yahoo.co.jp")
                ->send(new \App\Mail\CustomEmail($contact, "webexpert007@yahoo.co.jp", $company->name, $company));
            // \App\Models\NotificationLog::updateOrCreate([
            //     'contact_id' => $contact->id,
            //     'email'      => "webexpert007@yahoo.co.jp"
            // ], [
            //     'status' => 'Send',
            //     'message_id' => ''
            // ]);
        } catch (\Throwable $e) {
            $this->info($e->getMessage());
        }
        return 0;
    }
}
