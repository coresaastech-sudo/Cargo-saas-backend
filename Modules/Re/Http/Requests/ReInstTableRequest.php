<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstTableRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable',
            'name' => 'required',
            'name2' => 'nullable',
            'tablename' => 'required',
            'description' => 'nullable',
            'allattribute' => 'nullable',
            'fields' => 'nullable',
            'fields.*.name' => 'required',
            'fields.*.fieldname' => 'required',
            'fields.*.type' => 'required',
        ];
    }
    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'tablename.required' => ResponseCodeEnum::required,
            'fields.required' => ResponseCodeEnum::required
        ];
    }
}
