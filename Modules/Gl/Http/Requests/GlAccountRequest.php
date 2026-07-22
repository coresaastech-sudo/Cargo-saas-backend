<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'acntno' => 'required',
            'class' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'type' => 'required',
            'statusid' => 'nullable',
            'listorder' => 'nullable',
            'addinfo' => 'nullable',
            'addinfo2' => 'nullable',
            'catcode' => 'nullable',
            'centerbankaccount' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'acntno.required' => ResponseCodeEnum::required,
            'class.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
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
