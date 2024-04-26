<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactTemplate extends Model
{
    protected $table = 'contact_templates';

    protected $fillable = [
        'surname',
        'lastname',
        'fu_surname',
        'fu_lastname',
        'hi_surname',
        'hi_lastname',
        'company',
        'email',
        'template_title',
        'title',
        'myurl',
        'content',
        'homepageUrl',
        'area',
        'attachment',
        'postalCode1',
        'postalCode2',
        'address',
        'address1',
        'address2',
        'phoneNumber1',
        'phoneNumber2',
        'phoneNumber3',
        'date',
        'time',
    ];
}
