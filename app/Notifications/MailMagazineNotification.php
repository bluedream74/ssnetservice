<?php

namespace App\Notifications;

use App\Mail\MailTemplate;
use App\Models\MailTemplate as MailModel;
use App\Notifications\BaseNotification;
use Illuminate\Support\Str;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MailMagazineNotification extends BaseNotification
{
    public $uniqueId;
    public $contactId;
    public $email;
    public $companyName;

    private $contact;

    public function __construct($contact, $email, $name)
    {
        $this->uniqueId = Str::random(16);
        $this->contactId = $contact->id;
        $this->email = $email;
        $this->companyName = $name;

        $this->contact = $contact;
    }

    public function toMail($notifiable)
    {
        // $data = $this->handleContent(array_merge($this->data, []), $this->data['content'], $this->data['subject']);

        // return (new MailTemplate($data))->to($this->data['email']);
        $content = str_replace('%company_name%', $this->companyName, $this->contact->content);
        $uniqueId = $this->uniqueId;
        $email = $this->email;
        $contactId = $this->contact->id;

        if (isset($this->contact->attachment)) {
            return (new MailMessage())
                ->withSwiftMessage(function ($message) use ($uniqueId) {
                    $message->getHeaders()->addTextHeader('x-ses-configuration-set', config('services.ses.configuration_set'));
                    $message->getHeaders()->addTextHeader('unique-id', $uniqueId);
                })
                ->subject($this->contact->title)
                ->attach(realpath("storage/app/public/{$this->contact->attachment}"))
                ->view('emails.new_mail_template', compact('content', 'email', 'contactId'));
        }

        return (new MailMessage())
                ->withSwiftMessage(function ($message) use ($uniqueId) {
                    $message->getHeaders()->addTextHeader('x-ses-configuration-set', config('services.ses.configuration_set'));
                    $message->getHeaders()->addTextHeader('unique-id', $uniqueId);
                })
                ->subject($this->contact->title)
                ->view('emails.new_mail_template', compact('content', 'email', 'contactId'));
    }
}
