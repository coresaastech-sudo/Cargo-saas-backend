<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstContactRequest extends FormRequest
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
            'contacttype' => 'required',
            'fname' => 'required',
            'lname' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'userid' => 'nullable',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'contacttype.required' => ResponseCodeEnum::required,
            'fname.required' => ResponseCodeEnum::required,
            'lname.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
            'phone.required' => ResponseCodeEnum::required,
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
