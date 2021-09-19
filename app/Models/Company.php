<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class Company extends Model
{
    use Sortable;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'url',
        'contact_form_url',
        'source',
        'status',
        'area'
    ];
    public $sortable = [
        'name',
        'url',
        'source',
        'area',
    ];

    public function emails()
    {
        return $this->hasMany(CompanyEmail::class);
    }

    public function valid_emails()
    {
        return $this->hasMany(CompanyEmail::class)->where('is_verified', 1);
    }

    public function phones()
    {
        return $this->hasMany(CompanyPhone::class);
    }

    public function getSourceLabelAttribute()
    {
        return getSources(true)[intval($this->source)];
    }
}
