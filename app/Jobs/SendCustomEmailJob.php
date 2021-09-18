<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Support\Facades\Notification;

class SendCustomEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var String
     */
    private $email;

    /**
     * @var BaseNotification
     */
    private $notification;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        $email,
        BaseNotification $notification
    )
    {
        $this->email = $email;
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // try {
            Notification::route('mail', $this->email)->notify($this->notification);
        // } catch (\Throwable $e) {
        //     // \Log::error($e->getMessage());
        // }
    }
}
