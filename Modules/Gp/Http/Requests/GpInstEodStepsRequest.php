<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstEodStepsRequest extends FormRequest
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
            'orderno' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'stepdesc' => 'nullable',
            'controller' => 'required',
            'function' => 'required',
            'exturl' => 'nullable',
            'runfreq' => 'required',
            'proctype' => 'nullable',
            'sqlscript' => 'nullable',
            'runmonth' => 'nullable',
            'runday' => 'nullable',
            'sendsms' => 'nullable',
            'sendemail' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'orderno.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'controller.required' => ResponseCodeEnum::required,
            'function.required' => ResponseCodeEnum::required,
            'runfreq.required' => ResponseCodeEnum::required,
            'modifyopt.required' => ResponseCodeEnum::required,
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
