<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlReportConfListRequest extends FormRequest
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
            'colcount' => 'required',
            'AC' => 'required',
            'listorder' => 'nullable',
            'statusid' => 'nullable',
            'instid' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'AC.required' => ResponseCodeEnum::required,
            'colcount.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
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
