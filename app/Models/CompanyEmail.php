<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyEmail extends Model
{
    protected $table = 'company_emails';

    protected $fillable = [
        'company_id',
        'email',
        'is_valid',
        'is_verified'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
