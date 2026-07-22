<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustSignAcntRequest extends FormRequest
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
            'custno' => 'required',
            'signids' => 'nullable|array',
            'acnt_module' => 'required',
            'acntno'=> 'required'
        ];
    }

    public function messages()
    {
        return [
            'custno.required' => ResponseCodeEnum::required,
            'signids.required' => ResponseCodeEnum::required,
            'acnt_module.required' => ResponseCodeEnum::required,
            'acntno.required' => ResponseCodeEnum::required
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
