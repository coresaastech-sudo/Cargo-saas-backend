<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstCurRateRequest extends FormRequest
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
            'rtypecode' => 'required',
            'curcode' => 'required',
            'salerate' => 'required|numeric',
            'buyrate' => 'required|numeric',
            'avgrate' => 'required|numeric',
            'avgrateend' => 'required|numeric',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'curcode.required' => ResponseCodeEnum::required,
            'rtypecode.required' => ResponseCodeEnum::required,
            'salerate.required' => ResponseCodeEnum::numeric,
            'buyrate.required' => ResponseCodeEnum::numeric,
            'avgrate.required' => ResponseCodeEnum::numeric,
            'avgrateend.required' => ResponseCodeEnum::numeric,
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
