<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdEodLogDetailRequest extends FormRequest
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
            'acntno' => 'required',
            'acntbrchno' => 'required',
            'errdesc' => 'nullable',
            'ACTION_CODE' => 'required',
            'errtype' => 'required',
            'orderno' => 'required',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'eoddate.required' => ResponseCodeEnum::required,
            'stepno.required' => ResponseCodeEnum::required,
            'acntno.required' => ResponseCodeEnum::required,
            'acntbrchno.required' => ResponseCodeEnum::required,
            'ACTION_CODE.required' => ResponseCodeEnum::required,
            'errtype.required' => ResponseCodeEnum::required,
            'orderno.required' => ResponseCodeEnum::required,
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
