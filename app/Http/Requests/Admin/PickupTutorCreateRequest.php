<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class PickupTutorCreateRequest extends BaseRequest
{
    public function rules()
    {
        return [
            'user_id' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => __('admin/user/tutor.messages.pickup_required'),
        ];
    }

}
