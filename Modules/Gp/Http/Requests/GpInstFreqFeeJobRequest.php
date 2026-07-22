<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstFreqFeeJobRequest extends FormRequest
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
            'feecode' => 'required',
            'execfreq' => 'required',
            'proctype' => 'required',
            'rtypecode' => 'nullable',
            'formula' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'feecode.required' => ResponseCodeEnum::required,
            'execfreq.required' => ResponseCodeEnum::required,
            'proctype.required' => ResponseCodeEnum::required,
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
