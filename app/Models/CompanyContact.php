<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyContact extends Model
{
    protected $table = 'company_contacts';

    protected $fillable = [
        'company_id',
        'contact_id',
        'is_delivered'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
