<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstTariffRequest extends FormRequest
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
            'depend' => 'required',
            'interval' => 'required',
            'amount' => 'required',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'depend.required' => ResponseCodeEnum::required,
            'interval.required' => ResponseCodeEnum::required,
            'amount.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
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
