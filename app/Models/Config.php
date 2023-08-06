<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    //
    protected $table = 'config';

    protected $fillable = [
        'start',
        'end',
        'mailLimit',
        'checkContactForm'
    ];
}
