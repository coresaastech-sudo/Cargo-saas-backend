<?php

namespace Modules\Ap\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApInstStopServiceRequest extends FormRequest
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
            'instid' => 'nullable',
            'name' => 'required',
            'prod_code' => 'required',
            'prod_type' => 'required',
            'operation' => 'required',
            'description' => 'required',
            'begin_date' => 'required',
            'end_date' => 'required',
            'created_by' => 'nullable',
            'updated_by' => 'nullable',
            'statusid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'name.reguired' => ResponseCodeEnum::required,
            'prod_code.reguired' => ResponseCodeEnum::required,
            'prod_type.reguired' => ResponseCodeEnum::required,
            'operation.reguired' => ResponseCodeEnum::required,
            'description.reguired' => ResponseCodeEnum::required,
            'begin_date.reguired' => ResponseCodeEnum::required,
            'end_date.reguired' => ResponseCodeEnum::required,

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
