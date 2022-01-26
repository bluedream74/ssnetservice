<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'status',
        'message_id',
        'contact_id',
        'email'
    ];
}