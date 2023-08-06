<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryFeeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'delivery_fee_main'        => ['nullable', 'integer', 'min: 0', 'max: 50000'],
            'delivery_fee_remote'      => ['nullable', 'integer', 'min: 0', 'max: 50000'],
        ];
    }

    public function attributes()
    {
        return [
            'delivery_fee_main'        => '本島',
            'delivery_fee_remote'      => '離島',
        ];
    }
}
