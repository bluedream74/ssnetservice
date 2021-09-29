<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\PostalCode;

class TutorEditRequest extends FormRequest
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
            'users'             => ['required', 'array'],
            'users.email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->input('id') . ',id,deleted_at,NULL'],
            'profile'           => ['nullable', 'array'],
            'profile.first_name'        => ['required', 'string', 'max:255'],
            'profile.last_name'        => ['required', 'string', 'max:255'],
            'profile.company'        => ['required', 'string', 'max:255'],
            'profile.postal_code'        => ['required', 'string', 'max:255', new PostalCode()],
            'profile.address'        => ['required', 'string', 'max:255'],
            'profile.gender'        => ['required', 'string', 'max:255'],
            'profile.birthday'       => ['required'],
            'profile.profile'   => ['nullable', 'string'],
            'password'          => ['nullable', 'string', 'min:8', 'max:255'],
            'category'          => ['required', 'array'],
            'photo'             => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
        ];
    }

    public function attributes()
    {
        return [
            'user.email'        => 'メールアドレス',
            'profile.birthday'       => '生年月日',
            'profile.first_name'         => 'お名前(姓)',
            'profile.last_name'         => 'お名前(名)',
            'profile.postal_code'       => '郵便番号',
            'profile.company'           => '所属',
            'profile.address'           => '住所',
            'profile.gender'            => '性別',
            'password'          => 'パスワード',
            'category'          => 'カテゴリー',
            'photo'             => 'アイキャッチ画像',
        ];
    }

}
