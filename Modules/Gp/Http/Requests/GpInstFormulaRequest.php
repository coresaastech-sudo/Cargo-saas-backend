<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstFormulaRequest extends FormRequest
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
            'instid' => 'nullable',
            'name' => 'required',
            'name2' => 'required',
            'formula' => 'required',
            'type' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'formula.required' => ResponseCodeEnum::required,
            'type.required' => ResponseCodeEnum::required,
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
