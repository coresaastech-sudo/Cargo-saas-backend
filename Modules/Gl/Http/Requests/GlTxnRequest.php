<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlTxnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'txndesc' => 'nullable',

            'curcode' => 'required',
            'brchno' => 'required',
            'rate' => 'nullable',
            'txndate' => 'nullable|date',
            'isclosebalance' => 'nullable',
            'txns' => 'required|array',
            'txns.*.acntno' => 'required',
            'txns.*.amount' => 'required',
            'txns.*.txndesc' => 'required',
            'txns.*.unit' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'brchno.required' => ResponseCodeEnum::required,
            'txns.*.acntno.required' => ResponseCodeEnum::required,
            'txns.*.amount.required' => ResponseCodeEnum::required,
            'txns.*.txndesc.required' => ResponseCodeEnum::required,
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
