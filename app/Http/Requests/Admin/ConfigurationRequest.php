<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigurationRequest extends BaseRequest
{
    /**
     * @param Request $request
     * @return string[]
     */
    public function rules(Request $request)
    {
        return [
            'meta' => [
                'required',
                'regex:/^[a-zA-Z_]+$/',
                Rule::unique('configs', 'meta')->ignore($request->get('config_id')),
            ],
            'value' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'meta.required' => 'metaが必要です。',
            'meta.unique' => 'metaが必要です。',
            'meta.regex' => '「_」と英文字のみ利用できます。',
        ];
    }

    public function attributes()
    {
        return [
            'meta'            => 'Meta',
            'value'           => 'Value',
        ];
    }
}
