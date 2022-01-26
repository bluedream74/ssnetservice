<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSent;
use App\Models\NotificationLog;

class LogNotification
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(NotificationSent $event)
    {
        $notification = $event->notification->uniqueId;

        NotificationLog::updateOrCreate([
            'contact_id' => $event->notification->contactId,
            'email'      => $event->notification->email
        ], [
            'message_id' => $event->notification->uniqueId,
        ]);
    }
}