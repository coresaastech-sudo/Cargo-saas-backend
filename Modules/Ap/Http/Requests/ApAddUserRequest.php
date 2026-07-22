<?php

namespace Modules\Ap\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApAddUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'instid' => 'required',
            'phonenumber' => 'required',
            'position' => 'required',
            'employer' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'instid.required' => ResponseCodeEnum::required,
            'phonenumber.required' => ResponseCodeEnum::required,
            'position.required' => ResponseCodeEnum::required,
            'employer.required' => ResponseCodeEnum::required,
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
