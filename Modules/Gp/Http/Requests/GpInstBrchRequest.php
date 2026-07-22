<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstBrchRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'brchno' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'dirname' => 'nullable',
            'dirname2' => 'nullable',
            'begindate' => 'required',
            'phone' => 'nullable',
            'fax' => 'nullable',
            'email' => 'nullable',
            'isonline' => 'required',
            'bankcode' => 'nullable',
            'blevel' => 'nullable',
            'biccode' => 'nullable',
            'doestrade' => 'nullable',
            'listorder' => 'nullable',
            'state' => 'nullable',
            'region' => 'nullable',
            'subregion' => 'nullable',
            'address' => 'nullable',
            'zipcode' => 'nullable',
            'taxregion'=>'nullable',
            'taxsubregion'=>'nullable',
            'w3w' => 'nullable',
            'instid' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'brchno.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'begindate.required' => ResponseCodeEnum::required,
            'isonline.required' => ResponseCodeEnum::required,
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
