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
        return ["カテゴリ","子カテゴリ", "会社名", "URL","お問い合わせフォームのURL",'MYURLクリック', "エリア","ステータス", "電話番号"];
    }

    /**
    * @var CompanyContact $CompanyContact
    */
    public function map($CompanyContact): array
    {
        $company = $CompanyContact->company;
        $res = array();
        $res[] = $company->source;
        $res[] = $company->subsource;
        $res[] = $company->name;
        $res[] = $company->url;
        $res[] = $company->contact_form_url;
        $res[] = (string)$CompanyContact->counts;
        $res[] = $company->area;
        if ($CompanyContact->is_delivered == 1) {
            $res[] = "送信失敗";
        } elseif ($CompanyContact->is_delivered == 2) {
            $res[] = "配信済み";
        } elseif ($CompanyContact->is_delivered == 0) {
            $res[] = "送信予定";
        }
        $res[] = $company->phones()->count() > 0 ? $company->phones()->first()->phone : '';
    
        return $res;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = CompanyContact::where('contact_id', $this->contact->id)->whereIn('company_id', $this->contact->companies()->pluck('company_id'));
        if (!empty($value = Arr::get($this->params, 'status'))) {
            $query->where('is_delivered', $value);
        }
        return $query->get();
    }
}
