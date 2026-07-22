<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cr\Rules\ValidRegisterValue;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustIndRequest extends FormRequest
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
            'image' => 'nullable',
            'id1' => ['required', new ValidRegisterValue()],
            'id2' => ['required', new ValidRegisterValue()],
            'id1typecode' => 'required',
            'id2typecode' => 'required',
            'familyname' => 'required',
            'familyname2' => 'required',
            'name' => 'required',
            'name2' => 'required',
            'lname' => 'required',
            'lname2' => 'required',
            'sexcode' => 'required',
            'birthdate' => 'nullable|date:Y-m-d',
            'segcode' => 'required',
            'bl' => 'nullable',
            'loancount' => 'nullable',
            'inducode' => 'required',
            'indusubcode' => 'required',
            'catcode' => 'nullable',
            'handphone' => 'nullable',
            'email' => 'required|email',
            'titlecode' => 'nullable',
            'langcode' => 'nullable',
            'nationcode' => 'nullable',
            'educode' => 'nullable',
            'profession' => 'nullable',
            'countrycode' => 'required',
            'maritalstatuscode' => 'required',
            'familymembercount' => 'nullable',
            // 'statusid' => 'required',
            'sourcecode' => 'nullable',
            // 'instid' => 'required',
            'txndate' => 'nullable|date:Y-m-d',
            'lasttxndate' => 'nullable',
            'brchno' => 'nullable',
            // 'tellername' => 'required',
            'ispolitical' => 'nullable',
            // 'prevstatusid' => 'required',
            'workplace' => 'nullable',
            'position' => 'nullable',
            'card' => 'nullable',
            'lastrenewdate' => 'nullable',
            'managerno' => 'nullable',
            'manager_name' => 'nullable',
            'hidden' => 'nullable',
            'partner_type' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'id1.required' => ResponseCodeEnum::required,
            'id2.required' => ResponseCodeEnum::required,
            'id1typecode.required' => ResponseCodeEnum::required,
            'id2typecode.required' => ResponseCodeEnum::required,
            'familyname.required' => ResponseCodeEnum::required,
            'familyname2.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'name2.required' => ResponseCodeEnum::required,
            'lname.required' => ResponseCodeEnum::required,
            'lname2.required' => ResponseCodeEnum::required,
            'titlecode.required' => ResponseCodeEnum::required,
            'birthdate.date' => ResponseCodeEnum::date,
            'sexcode.required' => ResponseCodeEnum::required,
            'segcode.required' => ResponseCodeEnum::required,
            'countrycode.required' => ResponseCodeEnum::required,
            'inducode.required' => ResponseCodeEnum::required,
            'indusubcode.required' => ResponseCodeEnum::required,
            'maritalstatuscode.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
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
