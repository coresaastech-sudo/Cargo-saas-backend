<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstSuspRequest extends FormRequest
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
            'acntcode' => 'required',
            'brchno' => 'nullable',
            'curcode' => 'nullable',
            'acnttype' => 'required',
            'acntno' => 'required',
            'acntdesc' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'acntcode.required' => ResponseCodeEnum::required,
            'acnttype.required' => ResponseCodeEnum::required,
            'acntno.required' => ResponseCodeEnum::required,
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
