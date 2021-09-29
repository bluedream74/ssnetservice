<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Mail\MailTemplate;
use App\Models\MailTemplate as MailModel;
use App\Notifications\BaseNotification;

class CustomEmailNotification extends BaseNotification
{
    use Queueable;

    public function __construct($data, $template)
    {
        $this->data = $data;
        $this->template = $template;
    }

    public function toMail($notifiable)
    {
        $template = MailModel::where('slug', $this->template)->first();
        $data = $this->handleContent(array_merge($this->data, [
            'user_name' => $notifiable->name,
            'app_name' => config('app.name'),
        ]), $template->content, $template->subject);

        return (new MailTemplate($data))->to($notifiable->email);
    }
}
