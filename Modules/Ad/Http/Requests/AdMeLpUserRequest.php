<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdMeLpUserRequest extends FormRequest
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
            'password' => 'nullable',
            'userid' => 'required',
            'startdate' => 'required',
            'enddate' => 'required',
            'posno' => 'required',
            'roleno' => 'required',
            'instid' => 'nullable',
            'ispreview' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'userid.required' => ResponseCodeEnum::required,
            'startdate.required' => ResponseCodeEnum::required,
            'enddate.required' => ResponseCodeEnum::required,
            'posno.required' => ResponseCodeEnum::required,
            'roleno.required' => ResponseCodeEnum::required,
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
