<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormsCreateRequest extends FormRequest
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
            'title' => 'required',
            'form'       => 'required',
            'admin_to' => 'required',
            'admin_from' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'form.required'       => 'フォームを入力してください。',
            'admin_to.required' => '管理者宛メールの送信先は必須です。',
            'admin_from,required' => '管理者宛メールの送信元は必須です'
        ];
    }

}
