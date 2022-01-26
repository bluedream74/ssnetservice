<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    const ADMINISTRATOR = 'administrator';
    const TUTOR = 'tutor';
    const STUDENT = 'user';

    protected $fillable = [
        'name',
    ];
}
