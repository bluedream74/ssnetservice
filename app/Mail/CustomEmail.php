<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CustomEmail extends Mailable
{
  use Queueable, SerializesModels;

  public $uniqueId;
  public $contactId;
  public $email;
  public $companyName;
  public $company;

  private $contact;

  public function __construct($contact, $email, $name, $company)
  {
    $this->uniqueId = Str::random(16);
    $this->contactId = $contact->id;
    $this->email = $email;
    $this->companyName = $name;
    $this->company = $company;

    $this->contact = $contact;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    $content = str_replace('%company_name%', $this->companyName, $this->contact->content);
    $uniqueId = $this->uniqueId;
    $email = $this->email;
    $contactId = $this->contact->id;
    $company = $this->company;

    $content = nl2br($content);

    if (isset($this->contact->attachment)) {
      return $this->subject($this->contact->title)
                  ->attach(realpath("storage/app/public/{$this->contact->attachment}"))
                  ->view('emails.new_mail_template', compact('content', 'email', 'contactId', 'company'));
    }

    return $this->subject($this->contact->title)
                ->view('emails.new_mail_template', compact('content', 'email', 'contactId', 'company'));
  }
}
