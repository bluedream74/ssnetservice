<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'          => ['required', 'string', 'max: 255'],
            'rate'          => ['required', 'integer', 'min: 0', 'max: 100'],
        ];
    }

    public function attributes()
    {
        return [
            'name'          => '税率名',
            'rate'          => '税率',
        ];
    }
}
