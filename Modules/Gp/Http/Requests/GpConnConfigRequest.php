<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpConnConfigRequest extends FormRequest
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
            'code' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'typeid' => 'required',
            'config' => 'required',
            'descr' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'code.required' => ResponseCodeEnum::required,
            'typeid.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'config.required' => ResponseCodeEnum::required,
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
