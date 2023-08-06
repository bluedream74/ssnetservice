<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\BaseNotification;
use App\Models\User;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var User
     */
    private $user;

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
        User $user,
        BaseNotification $notification
    )
    {
        $this->user = $user;
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
            $this->user->notify($this->notification);
        // } catch (\Throwable $e) {
        //     \Log::error($e->getMessage());
        // }
    }
}
