<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdPermReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'nullable',
            'AC' => 'required',
            'valuetype' => 'required',
            'userid' => 'nullable',
            'brchno' => 'nullable',
            'roleid' => 'required_if:valuetype,R',
            'showbrchno' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'AC.required' => ResponseCodeEnum::required,
            'roleid.required_if' => ResponseCodeEnum::required,
            'showbrchno.required' => ResponseCodeEnum::required,
            'valuetype.required' => ResponseCodeEnum::required,
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
