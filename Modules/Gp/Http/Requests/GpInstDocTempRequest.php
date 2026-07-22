<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstDocTempRequest extends FormRequest
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
            'name' => 'required',
            'name2' => 'nullable',
            'template' => 'required',
            'doctype' => 'required',
            'data' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'template.required' => ResponseCodeEnum::required,
            'doctype.required' => ResponseCodeEnum::required,
            'data.required' => ResponseCodeEnum::required,
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
