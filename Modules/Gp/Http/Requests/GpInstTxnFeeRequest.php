<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstTxnFeeRequest extends FormRequest
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
            'ACTION_CODE' => 'required',
            'feecode' => 'required',
            'deductcracnt' => 'nullable',
            'deductdracnt' => 'nullable',
            'feecalcamount' => 'nullable',
            'rtypecode' => 'nullable',
            'whenapply' => 'nullable',
            'formula' => 'nullable',
            'deductlnrepayacnt' => 'nullable',
            'debittxnamount' => 'nullable',
            'isbatchfee' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'ACTION_CODE.required' => ResponseCodeEnum::required,
            'feecode.required' => ResponseCodeEnum::required,
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
