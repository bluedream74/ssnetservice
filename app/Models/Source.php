<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
  protected $fillable = [
    'name',
    'url',
    'sort_no'
  ];
}
