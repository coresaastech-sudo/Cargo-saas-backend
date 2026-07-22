<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustSignRequest extends FormRequest
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
            'image' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'sign_level' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'custid.required' => ResponseCodeEnum::required,
            'image.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'sign_level.required' => ResponseCodeEnum::required,
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
