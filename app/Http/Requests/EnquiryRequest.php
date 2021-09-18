<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Tel;
use App\Rules\Kana;

class EnquiryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'helps'                     => ['required', 'array'],
            'helps.email'               => ['required', 'string', 'email', 'max:255'],
            // 'helps.name'                => ['required', 'string', 'max:255'],
            // 'helps.kana_name'           => ['required', 'string', 'max:2000', new Kana()],
            'helps.message'             => ['required', 'string', 'max:5000'],
            // 'phone_number'              => ['required', 'array', new Tel()],
        ];
    }

    public function attributes()
    {
        return [
            'helps.email'                    => 'メールアドレス',
            'helps.name'                    => 'お名前',
            'helps.kana_name'               => 'フリカナ',
            'helps.message'                 => 'お問い合わせ内容',
            'phone_number'                  => '電話番号'
        ];
    }
}
