<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustShareRequest extends FormRequest
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
            'sharetypecode' => 'required',
            'sharepercent' => 'nullable',
            'begindate' => 'required',
            'desc' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'custid.required' => ResponseCodeEnum::required,
            'custid2.required' => ResponseCodeEnum::required,
            'sharetypecode.required' => ResponseCodeEnum::required,
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
