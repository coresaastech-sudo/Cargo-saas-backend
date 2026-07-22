<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstUserRoleRequest extends FormRequest
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
            "userid" => 'required',
            "roleid" => 'required',
            "startdate" => 'required',
            "enddate" => 'required',
            'statusid' => 'nullable|in:0,1'
        ];
    }

    public function messages()
    {
        return [
            'userid.required' => ResponseCodeEnum::required,
            'roleid.required' => ResponseCodeEnum::required,
            'startdate.required' => ResponseCodeEnum::required,
            'enddate.required' => ResponseCodeEnum::required,
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
