<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BlogRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'author_name' => 'nullable|string|max:255',
            'content' => 'required|string',
            'thumbnail' => 'required|image|max:5120',
            'status' => 'required|integer',
        ];
    }
}
