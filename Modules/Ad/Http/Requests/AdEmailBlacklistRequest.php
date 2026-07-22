<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdEmailBlacklistRequest extends FormRequest
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
            'emailaddress' => 'required',
            'reason' => 'required',
            'desc' => 'required',
            'lastupdatetime' => 'nullable',
            'source' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'emailaddress.required' => ResponseCodeEnum::required,
            'reason.required' => ResponseCodeEnum::required,
            'desc.required' => ResponseCodeEnum::required
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
