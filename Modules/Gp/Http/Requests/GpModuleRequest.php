<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpModuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "moduleid" => 'required',
            "parentid" => 'nullable',
            "name" => 'required',
            "name2" => 'required',
            "weburl" => 'nullable',
            "webversion" => 'nullable',
            "moduleversion" => 'nullable',
            "listorder" => 'required',
            "typeid" => 'required',
            "statusid" => 'nullable',
            "AC" => 'required',
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
