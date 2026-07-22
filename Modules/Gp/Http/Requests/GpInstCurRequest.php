<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstCurRequest extends FormRequest
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
            'curcode' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'avgrate' => 'nullable',
            'gl' => 'required',
            'listorder' => 'nullable',
            'margintype' => 'required',
            'marginup' => 'nullable',
            'margindown' => 'nullable',
            'endrate' => 'nullable',
            'avgrateend' => 'nullable',
            'midrate' => 'nullable',
            'yeslimit' => 'nullable',
            'ismetal' => 'nullable',
            'isbase' => 'nullable',
            'ismain' => 'nullable',
            'marketrate' => 'nullable',
            'valuedateterm' => 'nullable',
            'showsidemenu' => 'nullable',
            'showonline' => 'nullable',
            'equivacct' => 'nullable',
            'fxprof' => 'nullable',
            'fxloss' => 'nullable',
            'rvprof' => 'nullable',
            'rvloss' => 'nullable',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'curcode.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'gl.required' => ResponseCodeEnum::required,
            'margintype.required' => ResponseCodeEnum::required,
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
