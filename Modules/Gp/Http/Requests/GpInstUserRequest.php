<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstUserRequest extends FormRequest
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
            'username' => 'required',
            'instid' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'isadmin' => 'nullable',
            'regno' => 'required',
            'iprest' => 'nullable',
            'passwordexp' => 'nullable',
            'startdate' => 'required',
            'enddate' => 'required',
            'name' => 'required',
            'lname' => 'required',
            'brchno' => 'required',
            'tokenlimit' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'username.required' => "VC000001",
            'instid.required' => "VC000007",
            'email.required' => "VC000013",
            'phone.required' => "VC000014",
            'regno.required' => ResponseCodeEnum::required,
            // Нэгдсэн хоосон гэсэн код өгөх
            'startdate.required' => ResponseCodeEnum::required,
            'enddate.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'lname.required' => ResponseCodeEnum::required,
            'brchno.required' => ResponseCodeEnum::required,
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
