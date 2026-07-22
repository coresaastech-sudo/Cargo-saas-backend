<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdHideRequest extends FormRequest
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
            'modulekey' => 'required',
            'module' => 'required',
            'valuetype' => 'required',
            'userid' => 'nullable',
            'brchno' => 'nullable',
            'roleid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'modulekey.required' => ResponseCodeEnum::required,
            'module.required' => ResponseCodeEnum::required,
            'valuetype.required' => ResponseCodeEnum::required,
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
