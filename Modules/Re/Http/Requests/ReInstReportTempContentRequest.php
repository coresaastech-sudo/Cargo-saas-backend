<?php

namespace Modules\Re\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ReInstReportTempContentRequest extends FormRequest
{
    public function rules()
    {
        return [
            'id' => 'nullable',
            'templateid' => 'required',
            'type' => 'required',
            'contentname' => 'required',

            'parentid' => 'nullable',

            'source' => 'required',

            'richtext' => 'required',
            'orientation' => 'required',
            'x' => 'nullable',
            'y' => 'nullable',
            'contentmargin' => 'nullable',

            'height' => 'nullable',
            'width' => 'nullable',

            'bordertypes' => 'nullable',
            'bordercolor' => 'nullable',
            'borderwidth' => 'nullable',
            'highlightcolor' => 'nullable',
            'maincolor' => 'nullable',
            'alternativecolor' => 'nullable',
            'tableheaderrepeat' => 'nullable',

            'colcount' => 'nullable',
            'colwidth' => 'nullable',
            'align' => 'nullable',

            'headerfontsize' => 'nullable',
            'datafontsize' => 'nullable',

            'textcolor' => 'nullable',
            'verticalalign' => 'nullable',
            'hasfooter' => 'nullable',
            'cellexpression' => 'nullable',

            'frameinfo' => 'nullable',
            'framepos' => 'nullable',

            'framecol' => 'nullable',
            'framerow' => 'nullable',

            'excelshift' => 'nullable',

            'position' => 'required',

            'hasheader' => 'required',

            'listorder' => 'required',

            'children.*' => 'nullable'
        ];
    }

    public function messages()
    {
        return [
            'templateid' => ResponseCodeEnum::required,
            'type' => ResponseCodeEnum::required,
            'source' => ResponseCodeEnum::required,
            'richtext' => ResponseCodeEnum::required,
            'orientation' => ResponseCodeEnum::required,
            'position' => ResponseCodeEnum::required,
            'hasheader' => ResponseCodeEnum::required,
            'hasfooter' => ResponseCodeEnum::required
        ];
    }
}
