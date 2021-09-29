<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\PostalCode;
use App\Rules\Tel;
use App\Rules\Birthday;
use App\Rules\Zipcode;

class StudentEditRequest extends FormRequest
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
            'users'                     => ['required', 'array'],
            'users.name'                => ['required', 'string', 'max:255'],
            'profile'                   => ['required', 'array'],
            'profile.first_name'        => ['required', 'string', 'max: 255'],
            'phone_number'              => ['required', 'array', new Tel()],
            'profile.gender'            => ['nullable'],
            'birthday'                  => ['nullable', 'array', new Birthday()],
            'address'                   => ['required', 'array'],
            'address.postal_code'       => ['required', 'array', new Zipcode()],
            'address.prefecture'        => ['required', 'string'],
            'address.address'           => ['required', 'string', 'max: 255'],
            'address.address2'          => ['nullable', 'string', 'max: 255'],
            'address.address3'          => ['nullable', 'string', 'max: 255'],
            'image'                     => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
        ];
    }

    public function attributes()
    {
        return [
            'users.name'                => 'ユーザー名',
            'profile.first_name'        => '氏名',
            'phone_number'              => '電話番号',
            'profile.gender'            => '性別',
            'birthday'                  => '生年月日',
            'address'                   => '住所',
            'address.postal_code'       => '郵便番号',
            'address.prefecture'        => '都道府県',
            'address.address'           => '市区町村',
            'address.address2'          => '市区町村以降',
            'address.address3'          => '建物名',
            'image'                     => 'プロフィールアイコン',
        ];
    }

}
