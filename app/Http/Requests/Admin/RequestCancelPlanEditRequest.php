<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RequestCancelPlanEditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'student_id'    => ['integer'],
            'status'        => ['in:' . \App\Enums\RequestCancelPlanStatus::CONFIRMED],
            'email'         => ['email', 'required', 'string', 'max:254'],
            'name'          => ['required', 'string', 'max:254'],
            'content'       => ['required', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'student_id'    => 'ユーザID',
            'status'        => 'ステータス',
            'email'         => 'メール',
            'name'          => '名前',
            'content'       => '内容',
        ];
    }
}
