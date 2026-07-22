<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpResponseCodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "code" => 'required',
            "name" => 'required',
            "name2" => 'required',
            "allowsvp" => 'required',
            "msg_type" => 'required',
        ];
    }

    public function messages()
    {
        return [
            'code.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'name2.required' => ResponseCodeEnum::required,
            'allowsvp.required' => ResponseCodeEnum::required,
            'msg_type.required' => ResponseCodeEnum::required,
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
