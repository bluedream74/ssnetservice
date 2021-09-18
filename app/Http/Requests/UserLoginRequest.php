<?php

namespace App\Http\Requests;

class UserLoginRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'password' => 'required',
            'email' => 'required|email',
        ];
    }

    public function attributes()
    {
        return [
            'email'        => 'メールアドレス',
            'password'     => 'パスワード'
        ];
    }
}
