<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstUpdateRequest extends FormRequest
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
            'name' => 'required',
            'name2' => 'nullable',
            'regno' => 'required',
            'nationid' => 'nullable',
            'stabledate' => 'required',
            'inst_typeid' => 'nullable',
            'license_typeid' => 'nullable',
            'email' => 'nullable',
            'phone' => 'nullable',
            'dir_name' => 'nullable',
            'dir_name2' => 'nullable',
            'color' => 'nullable',
            'logo' => 'nullable',
            'state' => 'nullable',
            'region' => 'nullable',
            'subregion' => 'nullable',
            'street' => 'nullable',
            'zipcode' => 'nullable',
            'w3w' => 'nullable',
            'listorder' => 'nullable',
            'cbegno' => 'required',
            'cendno' => 'required',
            'cnextno' => 'nullable',
            'acntbegno' => 'required',
            'acntendno' => 'required',
            'acntnextno' => 'nullable',
            'iaacntbegno' => 'required',
            'iaacntendno' => 'required',
            'iaacntnextno' => 'nullable',
            'appbegno' => 'required',
            'appendno' => 'required',
            'appnextno' => 'nullable',
            'collbegno' => 'required',
            'collendno' => 'required',
            'collnextno' => 'nullable',
            'deductionbegno' => 'required',
            'deductionendno' => 'required',
            'deductionnextno' => 'nullable',
            'copyingfrom' => 'nullable',
            'billstartdate' => 'nullable',
            'iscreate_invoice' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'regno.required' => ResponseCodeEnum::required,
            'stabledate.requried' => ResponseCodeEnum::required,
            'cbegno.required' => ResponseCodeEnum::required,
            'cendno.required' => ResponseCodeEnum::required,
            'acntbegno.required' => ResponseCodeEnum::required,
            'acntendno.required' => ResponseCodeEnum::required,
            'iaacntbegno.required' => ResponseCodeEnum::required,
            'iaacntendno.required' => ResponseCodeEnum::required,
            'appbegno.required' => ResponseCodeEnum::required,
            'appendno.required' => ResponseCodeEnum::required,
            'collbegno.required' => ResponseCodeEnum::required,
            'collendno.required' => ResponseCodeEnum::required,
            'deductionbegno.required' => ResponseCodeEnum::required,
            'deductionendno.required' => ResponseCodeEnum::required,
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
