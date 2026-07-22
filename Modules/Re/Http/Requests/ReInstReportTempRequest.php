<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable',
            'ACTION_CODE' => 'nullable',
            'name' => 'required',
            'name2' => 'nullable',
            'dimensionid' => 'required',
            'orientation' => 'required',
            'pagemargin' => 'nullable',
            'hasheader' => 'required',
            'headersize' => 'nullable',
            'headerrepeat' => 'nullable',
            'hasfooter' => 'required',
            'font' => 'nullable',
            'footersize' => 'nullable',
            'footerrepeat' => 'nullable',
            'contentheight' => 'nullable',
            'exporttype' => 'nullable',
            'module' => 'required',
            'groupid' => 'nullable',
            'version' => 'required',
            'isbackground' => 'nullable',
            'code' => 'nullable|required_if:version,3',
        ];
    }
    public function messages()
    {
        return [
            'name.required' => ResponseCodeEnum::required,
            'dimensionid.required' => ResponseCodeEnum::required,
            'orientation.required' => ResponseCodeEnum::required,
            'hasheader.required' => ResponseCodeEnum::required,
            'hasfooter.required' => ResponseCodeEnum::required,
            'version.required' => ResponseCodeEnum::required,
        ];
    }
}
