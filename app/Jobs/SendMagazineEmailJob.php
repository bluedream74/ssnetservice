<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\BaseNotification;
use Carbon\Carbon;
use Throwable;
use App\Models\Company;
use Illuminate\Support\Facades\Notification;
use App\Mail\AmazonSes;
use Illuminate\Support\Facades\Mail;

class SendMagazineEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;

    /**
     * @var BaseNotification
     */
    private $notification;

    private $company;

    private $content;
    private $title;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        $email,
        $notification,
        // $title,
        // $content,
        Company $company
    )
    {
        $this->email = $email;
        // $this->title = $title;
        // $this->content = $content;
        $this->notification = $notification;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Notification::route('mail', $this->email)->notify($this->notification);
        // Mail::to($this->email)->send(new AmazonSes($this->title, $this->content));
    }

    public function failed(Throwable $exception)
    {
        // Send user notification of failure, etc...
        // \Log::error("HHHH:   " . $exception->getMessage());
        $this->company->emails()->where('email', $this->email)
                ->update([
                    'is_verified' => 0
                ]);
    }
}
