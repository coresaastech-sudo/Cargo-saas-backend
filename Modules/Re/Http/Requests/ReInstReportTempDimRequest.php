<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempDimRequest extends FormRequest
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
            'width' => 'required',
            'height' => 'required',
        ];
    }
    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'width.required' => ResponseCodeEnum::required,
            'height.required' => ResponseCodeEnum::required
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
