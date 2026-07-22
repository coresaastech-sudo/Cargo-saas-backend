<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustBankAccountRequest extends FormRequest
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
            'custid' => 'required',
            'custno' => 'nullable',
            'acnt_code' => 'required',
            'iban' => 'required',
            'acnt_name' => 'required',
            'bank_code' => 'required',
            'confirmed_at' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'custid.required' => ResponseCodeEnum::required,
            'acnt_code.required' => ResponseCodeEnum::required,
            'iban.required' => ResponseCodeEnum::required,
            'acnt_name.required' => ResponseCodeEnum::required,
            'bank_code.required' => ResponseCodeEnum::required,
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
