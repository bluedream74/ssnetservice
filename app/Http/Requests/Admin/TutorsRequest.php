<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TutorsRequest extends BaseRequest
{
    /**
     * @param Request $request
     * @return string[]
     */
    public function rules(Request $request)
    {
        $id = $request->get('tutor_id');

        $rules = [
            'profile.first_name'        => ['required', 'max: 255'],
            'profile.last_name'         => ['required', 'max: 255'],
            'users.email'               => [
                                                'required',
                                                'unique:users,email,' . $id . ',id,deleted_at,NULL'
                                            ],
            'users.is_active'           => 'required',
            'image'                     => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
        ];
        if ($request->isMethod('POST')) {
            $rules['users.password'] = 'required';
        }

        return $rules;
    }

    public function attributes()
    {
        return [
            'profile.first_name'            => 'お名前(姓)',
            'profile.last_name'             => 'お名前(名)',
            'users.email'                   => 'メールアドレス',
            'users.is_active'               => 'ステータス',
            'image'                         => 'プロフィール画像',
            'users.password'                => 'パスワード'
        ];
    }

}
