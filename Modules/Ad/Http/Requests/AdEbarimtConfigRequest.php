<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdEbarimtConfigRequest extends FormRequest
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
            'pos_api_address' => 'required',
            'pos_api_port' => 'required',
            'vat_percentage' => 'required',
            'registerno' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'pos_api_address.required' => ResponseCodeEnum::required,
            'pos_api_port.required' => ResponseCodeEnum::required,
            'vat_percentage.required' => ResponseCodeEnum::required
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
