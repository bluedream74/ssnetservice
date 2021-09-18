<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class PointConfigRequest extends BaseRequest
{
    public function rules()
    {
        return [
            'point_reduction_fee' => 'required|numeric|min:0',
            'point_transfer_fee' => 'required|numeric|min:0',
        ];
    }

    public function attributes()
    {
        return [
            'point_reduction_fee' => 'ポイント還元手数料',
            'point_transfer_fee' => 'ポイント振込手数料',
        ];
    }
}
