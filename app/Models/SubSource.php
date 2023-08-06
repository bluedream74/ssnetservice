<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubSource extends Model
{
    protected $table = 'subsources';

    protected $fillable = [
        'name',
        'source_id',
        'sort_no'
    ];

}
