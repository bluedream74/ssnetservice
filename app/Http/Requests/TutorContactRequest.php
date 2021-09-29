<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Tel;

class TutorContactRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'users'                     => ['required', 'array'],
            'users.email'               => ['required', 'string', 'email', 'max:255', 'unique:users,email,null,id,deleted_at,NULL'],
            'users.first_name'          => ['required', 'string', 'max:255'],
            'users.last_name'           => ['required', 'string', 'max:255'],
            // 'users.social_info'         => ['required', 'string', 'max:2000'],
            'users.message'             => ['required', 'string', 'max:5000'],
            'image'                     => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'category'                  => ['required', 'array'],
            'phone_number'              => ['required', 'array', new Tel()],
        ];
    }

    public function attributes()
    {
        return [
            'users.email'                    => 'メールアドレス',
            'users.first_name'              => 'お名前',
            'users.last_name'               => 'お名前',
            // 'users.social_info'             => 'TwitterリンクなどのソーシャルURL',
            'users.message'                 => 'お問い合わせ内容',
            'image'                         => 'プロフィール画像',
            'category'                      => '経歴資格タグ',
            'phone_number'                  => '電話番号'
        ];
    }
}
