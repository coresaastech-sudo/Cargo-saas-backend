<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cr\Rules\ValidRegisterValue;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustOrgRequest extends FormRequest
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
            'id1' => ['required', new ValidRegisterValue()],
            'id2' => ['nullable', new ValidRegisterValue()],
            'id1typecode' => 'required',
            'id2typecode' => 'nullable',
            'name' => 'required',
            'name2' => 'required',
            'segcode' => 'required',
            'bl' => 'nullable',
            'loancount' => 'nullable',
            'dirname' => 'nullable',
            'dirname2' => 'nullable',
            'dirlname' => 'nullable',
            'dirlname2' => 'nullable',
            'diridcode' => 'nullable',
            'dirid' => ['nullable', new ValidRegisterValue()],
            'diridcode2' => 'nullable',
            'dirid2' => ['nullable', new ValidRegisterValue()],
            'contactpname' => 'nullable',
            'contactppos' => 'nullable',
            'contactpphone' => 'nullable',
            'orgtypecode' => 'required',
            'inducode' => 'required',
            'indusubcode' => 'required',
            'countrycode' => 'required',
            'workphone' => 'nullable',
            'email' => 'nullable',
            'catcode' => 'nullable',
            'birthdate' => 'nullable',
            'lastrenewdate' => 'nullable',
            'brchno' => 'nullable',
            // 'tellername' => 'required',
            'empcount' => 'nullable',
            // 'statusid' => 'required',
            // 'prevstatusid' => 'required',
            'txndate' => 'nullable|date:Y-m-d',
            'lasttxndate' => 'nullable',
            'card' => 'nullable',
            'ispolitical' => 'nullable',
            'hidden' => 'nullable',
            'managerno' => 'nullable',
            'manager_name' => 'nullable',
            'sourcecode' => 'nullable',
            'tin' => 'nullable',
            'partner_type' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'id1typecode.required' => ResponseCodeEnum::required,
            'id1.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'name2.required' => ResponseCodeEnum::required,
            'orgtypecode.required' => ResponseCodeEnum::required,
            'segcode.required' => ResponseCodeEnum::required,
            'countrycode.required' => ResponseCodeEnum::required,
            'inducode.required' => ResponseCodeEnum::required,
            'indusubcode.required' => ResponseCodeEnum::required,
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
