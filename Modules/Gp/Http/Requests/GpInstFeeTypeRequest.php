<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstFeeTypeRequest extends FormRequest
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
            'collmeth' => 'required',
            'txncode' => 'required',
            'corrcode' => 'nullable',
            'brchapply' => 'required',
            'listorder' => 'nullable',
            'curcode' => 'nullable',
            'feetype' => 'nullable',
            'chid' => 'nullable',
            'description' => 'nullable',
            'feegroup' => 'nullable',
            'sources' => 'required|array',
            'sources.*.value' => 'required',
            'classification_code' => 'nullable',
            'sendvat' => 'nullable|boolean',
            'istax' => 'nullable|boolean'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'collmeth.required' => ResponseCodeEnum::required,
            'txncode.required' => ResponseCodeEnum::required,
            'brchapply.required' => ResponseCodeEnum::required,
            'sources.required' => ResponseCodeEnum::required,
            'sources.*.value.required' => ResponseCodeEnum::required,
            'sources.array' => ResponseCodeEnum::array,
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
