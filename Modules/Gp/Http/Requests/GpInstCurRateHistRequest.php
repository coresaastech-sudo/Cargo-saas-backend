<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstCurRateHistRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'typecode' => 'required',
            'curcode' => 'required',
            'salerate' => 'nullable',
            'buyrate' => 'nullable',
            'date' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'typecode.required' => ResponseCodeEnum::required,
            'curcode.required' => ResponseCodeEnum::required,
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
