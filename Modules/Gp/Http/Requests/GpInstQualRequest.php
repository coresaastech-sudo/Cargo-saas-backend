<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstQualRequest extends FormRequest
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
            'txncode' => 'required',
            'prodcode' => 'required',
            'acnttype1' => 'nullable',
            'acntno1' => 'nullable',
            'acnttype2' => 'nullable',
            'acntno2' => 'nullable',
            'clscode' => 'nullable',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'txncode.required' => ResponseCodeEnum::required,
            'prodcode.required' => ResponseCodeEnum::required,
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
