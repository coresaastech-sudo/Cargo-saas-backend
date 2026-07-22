<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstConstRequest extends FormRequest
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
            'name' => 'required',
            'name2' => 'nullable',
            'code' => 'nullable',
            'value' => 'required',
            'value_add1' => 'nullable',
            'value_add2' => 'nullable',
            'parent_id' => 'nullable',
            'listorder' => 'nullable',
            'is_show_front' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'value.required' => ResponseCodeEnum::required,
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
