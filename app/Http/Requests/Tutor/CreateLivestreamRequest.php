<?php

namespace App\Http\Requests\Tutor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CreateLivestreamRequest extends FormRequest
{
    public function rules(Request $request)
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'content'       => ['required', 'string'],
            'start_at'      => ['required'],
            'image'         => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 15360']
        ];
    }

    public function attributes()
    {
        return [
            'title'         => 'タイトル',
            'content'       => '内容',
            'start_dates'   => '予約可能時間',
            'image'         => 'サムネイル'
        ];
    }
}
