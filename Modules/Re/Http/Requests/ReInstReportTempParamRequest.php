<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempParamRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable',
            'templateid' => 'required',
            'permid' => 'required',
        ];
    }
    public function messages()
    {
        return [
            'templateid' => ResponseCodeEnum::required,
            'isnull' => ResponseCodeEnum::required,
            'evaluate' => ResponseCodeEnum::required,
            'hasinput' => ResponseCodeEnum::required,
            'hascondition' => ResponseCodeEnum::required
        ];
    }
}
