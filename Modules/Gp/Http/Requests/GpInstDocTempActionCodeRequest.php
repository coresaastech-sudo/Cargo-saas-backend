<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstDocTempActionCodeRequest extends FormRequest
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
            'doctempid' => 'required',
            'ACTION_CODE' => 'required',
            'response_type' => 'required',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'doctempid.required' => ResponseCodeEnum::required,
            'ACTION_CODE.required' => ResponseCodeEnum::required,
            'response_type.required' => ResponseCodeEnum::required,
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
