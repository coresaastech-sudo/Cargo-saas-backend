<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstCurPairRequest extends FormRequest
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
            'curcode' => 'required',
            'curcode2' => 'required',
            'instid' => 'nullable',
            'id' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'curcode.required' => ResponseCodeEnum::required,
            'curcode2.required' => ResponseCodeEnum::required,
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
