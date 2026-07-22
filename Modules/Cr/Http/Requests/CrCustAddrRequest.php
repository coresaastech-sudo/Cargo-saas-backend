<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustAddrRequest extends FormRequest
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
            'addrtypecode' => 'required',
            'apprtypecode' => 'nullable',
            'address' => 'required',
            'custid' => 'required',
            'state' => 'nullable',
            'region' => 'nullable',
            'subregion' => 'nullable',
            'zipcode' => 'required_if:addrtypecode,1|nullable|digits:5',
            'w3w' => 'nullable',
            'coord_lon' => [
                'nullable',
                'numeric',
                'between:-180,180'
            ],
            'coord_lat' => [
                'nullable',
                'numeric',
                'between:-90,90'
            ],
        ];
    }

    public function messages()
    {
        return [
            'addrtypecode.required' => ResponseCodeEnum::required,
            'address.required' => ResponseCodeEnum::required,
            'custid.required' => ResponseCodeEnum::required,
            'zipcode.required_if' => ResponseCodeEnum::required,
            'zipcode.digits' => ResponseCodeEnum::max,
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
