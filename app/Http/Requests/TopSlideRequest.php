<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopSlideRequest extends FormRequest
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
            'title'             => ['required', 'string', 'max:255'],
            'image01'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image02'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image03'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image04'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image05'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image06'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image07'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
            'image08'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360'],
        ];
    }

    public function attributes()
    {
        return [
            'title'             => 'タイトル',
            'image01'           => '画像１',
            'image01'           => '画像２',
            'image01'           => '画像３',
            'image01'           => '画像４',
            'image01'           => '画像５',
            'image01'           => '画像６',
            'image01'           => '画像７',
            'image01'           => '画像８',
        ];
    }
}
