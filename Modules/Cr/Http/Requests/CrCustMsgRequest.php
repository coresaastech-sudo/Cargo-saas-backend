<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustMsgRequest extends FormRequest
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
            'custid' => 'required_without:custno',
            'custno' => 'required_without:custid',
            'msgtypecode' => 'required',
            'msgnotecode' => 'nullable',
            'msg' => 'required',
            'msg2' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'custid.required_without' => ResponseCodeEnum::required,
            'custno.required_without' => ResponseCodeEnum::required,
            'msgtypecode.required' => ResponseCodeEnum::required,
            'msg.required' => ResponseCodeEnum::required,
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
