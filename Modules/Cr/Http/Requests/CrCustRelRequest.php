<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustRelRequest extends FormRequest
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
            'custid2' => 'required',
            'reltypecode' => 'required',
            'relsubtypecode' => 'required',
            'enddate' => 'nullable',
            'begindate' => 'required',
            'reldesc' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'custid.required' => ResponseCodeEnum::required,
            'custid2.required' => ResponseCodeEnum::required,
            'reltypecode.required' => ResponseCodeEnum::required,
            'relsubtypecode.required' => ResponseCodeEnum::required,
            'begindate.required' => ResponseCodeEnum::required,
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
