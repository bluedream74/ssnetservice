<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'products'              => ['required', 'array'],
            'products.title'        => ['required', 'string', 'max: 255'],
            'products.content'      => ['required'],
            'products.info'         => ['required', 'string', 'max: 255'],
            'products.price'        => ['required', 'integer', 'min: 0'],
            'products.usage_amount' => ['nullable', 'string', 'max: 255'],
            'products.revenue'      => ['required', 'integer', 'min: 0', 'max: 100'],
            'image'                 => [(is_null($this->input('id')) ? 'required' : 'nullable'), 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
        ];
    }

    public function attributes()
    {
        return [
            'products.title'        => 'タイトル',
            'products.content'      => '説明',
            'products.info'         => '製品情報',
            'products.price'        => '価格',
            'products.usage_amount' => '内容量',
            'image'                 => 'メディア',
            'products.revenue'      => 'カウンセラーの報酬'
        ];
    }
}
