<?php

namespace App\Http\Requests;

class BuyPointRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'point_quantity' => 'required|numeric',
            'payment_method' => 'required',
            'campaign_id' => 'nullable|numeric',
        ];
    }
}
