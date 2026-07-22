<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlTxnCurrRequest extends FormRequest
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
            'txndate' => 'nullable|date',
            'curcode' => 'required',
            'brchno' => 'required',
            'rate' => 'nullable',
            'txns' => 'required|array',
            'txns.*.acntno' => 'required',
            'txns.*.amount' => 'required',
            'txns.*.txndesc' => 'required',
            'txns.*.unit' => 'nullable',

            'contcurcode' => 'required',
            'contbrchno' => 'required',
            'contrate' => 'nullable',
            'conttxns' => 'required|array',
            'conttxns.*.acntno' => 'required',
            'conttxns.*.amount' => 'required',
            'conttxns.*.txndesc' => 'required',
            'conttxns.*.unit' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'curcode.required' => ResponseCodeEnum::required,
            'contcurcode.required' => ResponseCodeEnum::required,
            'contbrchno.required' => ResponseCodeEnum::required,
            'brchno.required' => ResponseCodeEnum::required,
            'txns.required' => ResponseCodeEnum::required,
            'txns.*.acntno.required' => ResponseCodeEnum::required,
            'txns.*.amount.required' => ResponseCodeEnum::required,
            'txns.*.txndesc.required' => ResponseCodeEnum::required,

            'conttxns.required' => ResponseCodeEnum::required,
            'conttxns.*.acntno.required' => ResponseCodeEnum::required,
            'conttxns.*.amount.required' => ResponseCodeEnum::required,
            'conttxns.*.txndesc.required' => ResponseCodeEnum::required,
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
