<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustSecretRequest extends FormRequest
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
            'questiontypecode' => 'nullable',
            'is_inputquestion' => 'nullable',
            'question' => 'nullable',
            'answer' => 'required',
            'custid' => 'required',
        ];
    }

    public function messages()
    {
        return [
            // 'questiontypecode.required' => ResponseCodeEnum::required,
            'answer.required' => ResponseCodeEnum::required,
            'custid.required' => ResponseCodeEnum::required,
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
