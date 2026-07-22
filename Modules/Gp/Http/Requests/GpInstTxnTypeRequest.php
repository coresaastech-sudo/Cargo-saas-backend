<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstTxnTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ACTION_CODE' => 'required_unless:moduleid,tr',
            'name' => 'required',
            'name2' => 'nullable',
            'txnopt' => 'nullable',
            'qualifier' => 'nullable',
            'acnttype1' => 'nullable',
            'acntno1' => 'nullable',
            'acnttype2' => 'nullable',
            'acntno2' => 'nullable',
            'moduleid' => 'required',
            'txntype' => 'nullable',
            'rtypecode' => 'nullable',
            'isbatchfee' => 'nullable',
            'batchfeetxncode' => 'nullable',
            'batchfeetxndesc' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'ACTION_CODE.required_if' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'moduleid.required' => ResponseCodeEnum::required,
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
