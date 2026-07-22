<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlChartRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'data' => 'required|array',
            'data.*.acntno' => 'required',
            'data.*.name' => 'required',
            'data.*.name2' => 'nullable',
            'data.*.statusid' => 'nullable',
            'data.*.listorder' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'data.*.acntno.required' => ResponseCodeEnum::required,
            'data.*.name.required' => ResponseCodeEnum::required,
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
