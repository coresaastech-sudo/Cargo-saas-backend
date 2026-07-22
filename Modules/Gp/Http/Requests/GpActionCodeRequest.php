<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpActionCodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "ACTION_CODE" => 'required',
            "name" => 'required',
            "name2" => 'required',
            "controller" => 'required',
            "function" => 'required',
        ];
    }

    public function messages()
    {
        return [
            'ACTION_CODE.required' => "VC000001",
            'name.required' => "VC000007",
            'name2.required' => "VC000013",
            'controller.required' => "VC000014",
            'function.required' => ResponseCodeEnum::required,
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
