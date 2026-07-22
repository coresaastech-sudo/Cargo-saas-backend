<?php

namespace Modules\Cr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustContactRequest extends FormRequest
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
            'contacttypecode' => 'required',
            'description' => 'nullable|max:300',
            'contact' => 'required',
            'custid' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'custid.required' => ResponseCodeEnum::required,
            'contacttypecode.required' => ResponseCodeEnum::required,
            'contact.required' => ResponseCodeEnum::required,
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
