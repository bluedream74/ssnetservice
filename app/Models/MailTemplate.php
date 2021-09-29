<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $fillable = [
        'title',
        'subject',
        'slug',
        'memo',
        'content'
    ];
}
