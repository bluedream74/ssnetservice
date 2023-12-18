<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Contact extends Model
{
    protected $table = 'contacts';

    protected $fillable = [
        'surname',
        'lastname',
        'fu_surname',
        'fu_lastname',
        'hi_surname',
        'hi_lastname',
        'company',
        'email',
        'title',
        'myurl',
        'content',
        'homepageUrl',
        'area',
        'contacts_num',
        'sender_name',
        'attachment',
        'is_confirmed',
        'postalCode1',
        'postalCode2',
        'address',
        'address1',
        'address2',
        'phoneNumber1',
        'phoneNumber2',
        'phoneNumber3',
        'date',
        'time',
    ];

    public function companies()
    {
        return $this->hasMany(CompanyContact::class, 'contact_id');
    }

    public function getTotalEmailsNumAttribute()
    {
        return \App\Models\CompanyEmail::whereIn('company_id', $this->companies()->pluck('company_id')->toArray())
                                    ->where('is_verified', 1)
                                    ->count();
    }

    public function success_companies()
    {
        return $this->companies()->where('is_delivered', 2);
    }

    public function failed_companies()
    {
        return $this->companies()->where('is_delivered', 1);
    }

    public function reserve_companies()
    {
        return $this->companies()->whereIn('is_delivered', [0,3,10]);
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class, 'contact_id');
    }

    public function open_logs()
    {
        return $this->hasMany(NotificationLog::class, 'contact_id')->where('status', 'Open');
    }

    public function getEmailDeliveryStatus($email)
    {
        $log = $this->logs()->where('email', $email)->first();

        if (empty($log)) return "";
        if (!isset($log->status)) return "不達";

        $values = [
            'Failed'        => '送信失敗',
            'Send'          => '送信済み',
            'Bounce'        => '拒否',
            'Complaint'     => 'スパムマーク',
            'Delivery'      => '配信済み',
            'Open'          => '開封済み',
            'Reject'        => 'AWSリジェクト'
        ];

        return $values[$log->status];
    }

    public function getFilePathAttribute()
    {
        if (isset($this->attachment)) return Storage::disk('public')->url($this->attachment);

        return "";
    }

    public function getRemainingCountAttribute()
    {
        $total = $this->total_emails_num - $this->logs()->count();
        return $total < 0 ? 0 : $total;
    }
}
