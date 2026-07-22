<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempParamInRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable',
            'templateid' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'input' => 'required',
            'forminputtype' => 'required',
            'listorder' => 'required',
            'hasinputcondition' => 'required',
            'inputid' => 'nullable',
            'inputcondition' => 'nullable',
            'dropdowndic' => 'nullable',
            'config' => 'nullable|json',
        ];
    }
    public function messages()
    {
        return [
            'templateid' => ResponseCodeEnum::required,
            'name' => ResponseCodeEnum::required,
            'input' => ResponseCodeEnum::required,
            'forminputtype' => ResponseCodeEnum::required,
            'config' => ResponseCodeEnum::date,
        ];
    }
}
