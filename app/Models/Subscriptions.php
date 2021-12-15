<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriptions extends Model
{
    //
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'stripe_id',
        'stripe_plan',
        'quantity',
    ];
}
