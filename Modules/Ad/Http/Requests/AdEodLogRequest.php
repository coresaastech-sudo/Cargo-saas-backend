<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdEodLogRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'eoddate' => 'required',
            'stepno' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'statusid' => 'required',
            'stepdesc' => 'nullable',
            'controller' => 'required',
            'function' => 'required',
            'exturl' => 'nullable',
            'useexturl' => 'nullable',
            'sqlscript' => 'nullable',
            'runmonth' => 'nullable',
            'runday' => 'nullable',
            'startdate' => 'nullable',
            'enddate' => 'nullable',
            'sendsms' => 'nullable',
            'sendemail' => 'nullable',
            'orderno' => 'nullable',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'eoddate.required' => ResponseCodeEnum::required,
            'stepno.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'controller.required' => ResponseCodeEnum::required,
            'function.required' => ResponseCodeEnum::required,
            'statusid.required' => ResponseCodeEnum::required,
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
