<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstGpRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'required',
            'itemname' => 'nullable',
            'itemdesc' => 'nullable',
            'itemdesc2' => 'nullable',
            'itemvalue' => 'required',
            'itemtype' => 'nullable',
            'itemadditional' => 'nullable',
            'itemadditional2' => 'nullable',
            'groupname' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => ResponseCodeEnum::required,
            'itemvalue.required' => ResponseCodeEnum::required,
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
