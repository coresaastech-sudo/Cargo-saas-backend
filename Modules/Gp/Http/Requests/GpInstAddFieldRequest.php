<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstAddFieldRequest extends FormRequest
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
            'typecode' => 'required_without:data',
            'name' => 'required_without:data',
            'name2' => 'nullable',
            'tagtype' => 'required_without:data',
            'taglen' => 'nullable',
            'tagmask' => 'nullable',
            'mandatory' => 'nullable',
            'listorder' => 'required_without:data|integer|min:0|max:2147483647',
            'defaultvalue' => 'nullable',
            'readonly' => 'nullable',
            'minvalue' => 'nullable',
            'maxvalue' => 'nullable',
            'code' => 'required_without:data',
            'descr' => 'nullable',
            'data' => 'nullable|array'
        ];
    }

    public function messages()
    {
        return [
            'typecode.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'tagtype.required' => ResponseCodeEnum::required,
            'code.required' => ResponseCodeEnum::required,
            'listorder.required' => ResponseCodeEnum::required,
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
