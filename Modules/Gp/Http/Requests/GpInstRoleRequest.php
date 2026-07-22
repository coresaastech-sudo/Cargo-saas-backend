<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "id" => 'nullable',
            "rolename" => 'required',
            "rolename2" => 'required',
            "listorder" => 'required',
            "instid" => 'nullable',
            "isadmin" => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'rolename.required' => ResponseCodeEnum::required,
            'rolename2.required' => ResponseCodeEnum::required,
            'listorder.required' => ResponseCodeEnum::required,
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
