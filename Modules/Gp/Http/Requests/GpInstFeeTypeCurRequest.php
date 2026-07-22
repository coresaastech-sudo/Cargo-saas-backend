<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstFeeTypeCurRequest extends FormRequest
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
            'feecode' => 'required',
            'curcode' => 'nullable',
            'calcmeth' => 'required|numeric',
            'perrate' => 'required_if:calcmeth,1',
            'flatrate' => 'required_if:calcmeth,2',
            'minfee' => 'required_if:calcmeth,1|required_if:calcmeth,4',
            'maxfee' => 'required_if:calcmeth,1|required_if:calcmeth,4',
            'feecurcode' => 'nullable',
            'vat_split_percent' => 'nullable|numeric|min:0',
            'vat_txncode' => 'nullable',
            'formula' => 'required_if:calcmeth,4',
            'ratetierdatas' => 'required_if:calcmeth,3|required_if:calcmeth,5|array',

            'ratetierdatas.*.intervalno' => 'required|numeric',
            'ratetierdatas.*.calcmeth' => 'required|numeric',
            'ratetierdatas.*.flatrate' => 'required_if:calcmeth,2',
            'ratetierdatas.*.perrate' => 'required_if:calcmeth,1',
            'ratetierdatas.*.loancount' => 'required_if:calcmeth,5',
            'ratetierdatas.*.minamount' => 'required|numeric',
            'ratetierdatas.*.maxamount' => 'required|numeric'
        ];
    }

    public function messages()
    {
        return [
            'feecode.required' => ResponseCodeEnum::required,
            'calcmeth.required' => ResponseCodeEnum::required,
            'perrate.required_if' => ResponseCodeEnum::required,
            'flatrate.required_if' => ResponseCodeEnum::required,
            'minfee.required_if' => ResponseCodeEnum::required,
            'maxfee.required_if' => ResponseCodeEnum::required,
            'formula.required_if' => ResponseCodeEnum::required,
            'ratetierdatas.required_if' => ResponseCodeEnum::required,
            'ratetierdatas.array' => ResponseCodeEnum::array,
            'ratetierdatas.*.intervalno.required' => ResponseCodeEnum::required,
            'ratetierdatas.*.minamount.required' => ResponseCodeEnum::required,
            'ratetierdatas.*.maxamount.required' => ResponseCodeEnum::required,
            'ratetierdatas.*.calcmeth.required' => ResponseCodeEnum::required,
            'ratetierdatas.*.flatrate.required_if' => ResponseCodeEnum::required,
            'ratetierdatas.*.perrate.required_if' => ResponseCodeEnum::required,
            'ratetierdatas.*.loancount.required_if' => ResponseCodeEnum::required,
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (($this->input('vat_split_percent') ?? 0) > 0 && empty($this->input('vat_txncode'))) {
                $validator->errors()->add('vat_txncode', ResponseCodeEnum::required);
            }
        });
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
