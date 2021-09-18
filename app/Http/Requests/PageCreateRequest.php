<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PageCreateRequest extends FormRequest
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
        $id = $this->input('id');

        return [
            'posts.title'               => ['required', 'max: 500'],
            'posts.slug'                => ['required', 'regex:/^[a-zA-Z0-9\_\-]*$/', 'unique:posts,slug,' . $id],
            'posts.content'             => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            'posts.title'               => 'タイトル',
            'posts.content'             => '本文',
            'posts.slug'                => 'パーマリンク',
        ];
    }
}
