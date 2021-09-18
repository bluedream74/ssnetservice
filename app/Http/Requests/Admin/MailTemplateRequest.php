<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MailTemplateRequest extends BaseRequest
{
    /**
     * @param Request $request
     * @return string[]
     */
    public function rules(Request $request)
    {
        return [
            'subject' => ['required'],
            'memo' => ['required'],
            'content' => ['required'],
            'line_content' => ['nullable'],
        ];
    }
}
