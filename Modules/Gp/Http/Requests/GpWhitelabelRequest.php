<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpWhitelabelRequest extends FormRequest
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
            'app_data' => 'required',
            'app_secret' => 'required',
            'app_identifier' => 'required',
            'instid' => 'nullable',
            'app_name' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
            'app_data.required' => ResponseCodeEnum::required,
            'app_secret.required' => ResponseCodeEnum::required,
            'app_identifier.required' => ResponseCodeEnum::required,
            'app_name.required' => ResponseCodeEnum::required,
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
