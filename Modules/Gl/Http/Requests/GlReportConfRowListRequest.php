<?php

namespace Modules\Gl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GlReportConfRowListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'report_conf_id' => 'required',
            'dataArray.*.id' => 'required',
            'dataArray.*.report_conf_id' => 'required',
            'dataArray.*.num' => 'nullable',
            'dataArray.*.name' => 'required',
            'dataArray.*.name2' => 'nullable',
            'dataArray.*.new' => 'required|in:0,1',
            'dataArray.*.isbegbal' => 'nullable',
            'dataArray.*.isbold' => 'required',
            'dataArray.*.listorder' => 'nullable',
            'dataArray.*.updated_by' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'dataArray' => ResponseCodeEnum::array,
            'dataArray.*.report_conf_id.required' =>ResponseCodeEnum::required,
            'dataArray.*.name' => ResponseCodeEnum::required,
            'dataArray.*.isbold.required' =>ResponseCodeEnum::required,
            'dataArray.*.id.required' => ResponseCodeEnum::required,
            'dataArray.*.new.required' => ResponseCodeEnum::required
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
