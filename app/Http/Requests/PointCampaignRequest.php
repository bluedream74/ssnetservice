<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PointCampaignRequest extends FormRequest
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
            'title'             => 'required',
            'start_campaign'    => 'required',
            'end_campaign'      => 'required',
            'price'             => 'integer',
            'point'             => 'integer',
        ];
    }

    public function messages()
    {
        return [
            'title.required'            => 'タイトルを入力してください。',
            'start_campaign.required'   => '開始日時を入力してください。',
            'end_campaign.required'     => '終了日時を入力してください。',
            'price.integer'             => '値段は数で入力してください。',
            'point.integer'             => '値段は数で入力してください。',
        ];
    }
}
