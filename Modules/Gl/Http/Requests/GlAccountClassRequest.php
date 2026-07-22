<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlAccountClassRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'class' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'type' => 'nullable',
            'balmoving' => 'nullable',
            'listorder' => 'nullable',
            'statusid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'class.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
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
