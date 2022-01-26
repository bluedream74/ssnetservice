<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingCreateRequest extends FormRequest
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
            'title'         => ['required', 'string', 'max:255'],
            'content'       => ['required', 'string'],
            'category_id'   => ['required', 'integer'],
            'start_dates'   => ['required', 'array'],
            'photo'         => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max: 5120']
        ];
    }

    public function attributes()
    {
        return [
            'title'             => 'タイトル',
            'category_id'       => 'カテゴリ',
            'content'           => '内容',
            'start_dates'       => '予約可能時間',
            'photo'             => 'サムネイル'
        ];
    }
}
