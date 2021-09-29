<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PresentRequest extends FormRequest
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
            'presents'                  => ['required', 'array'],
            'presents.name'             => ['required', 'string'],
            'presents.point'            => ['required', 'integer', 'min: 1'],
            'presents.status'           => ['required', 'integer', 'min: 0', 'max: 1'],
            'photo'                     => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 5120']
        ];
    }

    public function attributes()
    {
        return [
            'presents.name'             => 'プレゼント名',
            'presents.point'            => 'ポイント',
            'presents.status'           => 'ステータス',
            'photo'                     => 'アイコン'
        ];
    }

}
