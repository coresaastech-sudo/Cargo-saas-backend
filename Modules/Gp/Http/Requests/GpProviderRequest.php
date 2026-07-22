<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpProviderRequest extends FormRequest
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
            'connid' => 'required',
            'typeid' => 'required',
            'config' => 'required',
            'descr' => 'nullable',
            'sec1' => 'nullable',
            'sec2' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'code.required' => ResponseCodeEnum::required,
            'connid.required' => ResponseCodeEnum::required,
            'typeid.required' => ResponseCodeEnum::required,
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
