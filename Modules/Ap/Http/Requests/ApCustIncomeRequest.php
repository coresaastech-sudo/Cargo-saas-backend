<?php

namespace Modules\Ap\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApCustIncomeRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable|integer',
            'instid' => 'required|integer',
            'regno' => 'required|string',
            'cif' => 'required|string',
            'cust_userid' => 'required|integer',
            'type' => 'required|string|in:salary,sales,real',
            'source_name' => 'nullable|string',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'amount' => 'required|numeric|min:0',
            'fee' => 'nullable|numeric|min:0',
            'net_income' => 'required|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'regno.required' => ResponseCodeEnum::required,
            'cif.required' => ResponseCodeEnum::required,
            'cust_userid.required' => ResponseCodeEnum::required,
            'type.required' => ResponseCodeEnum::required,
            'year.required' => ResponseCodeEnum::required,
            'month.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'net_income.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ];
    }
}