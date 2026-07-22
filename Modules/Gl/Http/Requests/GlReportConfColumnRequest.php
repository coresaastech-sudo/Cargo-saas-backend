<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlReportConfColumnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'conf_detail_id' => 'required',
            'acntnos' => 'required_without:id',
            'id' => 'required_without:acntnos',
            'columnidx' => 'required_with:id',
            'acntno' => 'required_with:id',
            'type' => 'nullable',
            'multiply' => 'nullable',
            'isbegbal' => 'nullable',
            'istranbal' => 'nullable',
            'acntnos.*.columnidx' => 'required',
            'acntnos.*.acntno' => 'required',
            'acntnos.*.type' => 'nullable',
            'acntnos.*.multiply' => 'nullable',
            'acntnos.*.isbegbal' => 'nullable',
            'acntnos.*.istranbal' => 'nullable',
            'acntnos.*.conttxns' => 'nullable|array',
            'acntnos.*.conttxns.*.contacntno' => 'required_with:acntnos.*.conttxns|max:14',
            'acntnos.*.conttxns.*.conttrantype' => 'required_with:acntnos.*.conttxns|max:4',
            'conttxns' => 'nullable|array',
            'conttxns.*.contacntno' => 'required_with:conttxns|max:14',
            'conttxns.*.conttrantype' => 'required_with:conttxns|max:4',
        ];
    }

    public function messages()
    {
        return [
            'conf_detail_id.required' => ResponseCodeEnum::required,
            'acntnos.required_without' => ResponseCodeEnum::required,
            'acntnos.required' => ResponseCodeEnum::array,
            'acntnos.*.columnidx.required' => ResponseCodeEnum::required,
            'acntnos.*.acntno.required' => ResponseCodeEnum::required,
            'acntnos.*.conttxns.*.contacntno.required_with' => ResponseCodeEnum::required,
            'acntnos.*.conttxns.*.conttrantype.required_with' => ResponseCodeEnum::required,
            'conttxns.*.contacntno.required_with' => ResponseCodeEnum::required,
            'conttxns.*.conttrantype.required_with' => ResponseCodeEnum::required,
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
