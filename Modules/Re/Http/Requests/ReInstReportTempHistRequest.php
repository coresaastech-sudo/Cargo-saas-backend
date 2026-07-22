<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempHistRequest extends FormRequest
{
    public function rules()
    {
        return [
            'templateid' => 'required',
            'parameter' => 'required',
        ];
    }
    public function messages()
    {
        return [
            'templateid' => ResponseCodeEnum::required,
            'parameter' => ResponseCodeEnum::required
        ];
    }
}
