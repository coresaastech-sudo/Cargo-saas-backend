<?php

namespace Modules\Ad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class AdSvUserRequest extends FormRequest
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
            'userid' => 'required',
            'svuserid' => 'required',
            'svtype' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'userid.required' => ResponseCodeEnum::required,
            'svuserid.required' => ResponseCodeEnum::required,
            'svtype.required' => ResponseCodeEnum::required,
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
