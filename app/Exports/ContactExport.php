<?php

namespace App\Exports;

use App\Models\CompanyContact;
use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\NotificationLog;

class ContactExport implements FromCollection, WithHeadings, WithMapping
{
  protected $contact;
  protected $params = array();

  protected $values = [
    'Failed'        => '送信失敗',
    'Send'          => '送信済み',
    'Bounce'        => '拒否',
    'Complaint'     => 'スパムマーク',
    'Delivery'      => '配信済み',
    'Open'          => '開封済み',
    'Reject'        => 'AWSリジェクト'
  ];

  /**
  * Optional headers
  */
  private $headers = [
      'Content-Type' => 'text/csv',
  ];

  public function __construct($contact, $params)
  {
    $this->contact = $contact;
    $this->params = $params;
  }

  public function headings(): array
  {
    return ["カテゴリ", "会社名", "URL",  "電話番号", "ステータス"];
  }

  /**
  * @var CompanyContact $CompanyContact
  */
  public function map($CompanyContact): array
  {
    $company = $CompanyContact->company;

    $res = array();
    $res[] = $company->source;
    $res[] = $company->name;
    $res[] = $company->contact_form_url;
    // $res[] = $CompanyContact->email;
    $res[] = $company->phones()->count() > 0 ? $company->phones()->first()->phone : '';
    $res[] = $CompanyContact->is_delivered;
    return $res;
  }

  /**
  * @return \Illuminate\Support\Collection
  */
  public function collection()
  {
    $query = CompanyContact::whereIn('company_id', $this->contact->companies()->pluck('company_id'));
    if (!empty($value = Arr::get($this->params, 'status'))) {
      if ($value == 'Not') {
        $query = $query->whereIn('email', \App\Models\NotificationLog::where('contact_id', $this->contact->id)->whereNull('status')->pluck('email'));
      } else {
        $query = $query->whereIn('email', \App\Models\NotificationLog::where('contact_id', $this->contact->id)->where('status', $value)->pluck('email'));
      }
    }
    return $query->get();
  }
}
