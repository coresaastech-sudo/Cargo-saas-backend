<?php

namespace Modules\Gp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class GpInstInvoiceRequest extends FormRequest
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
            'instid' => 'required',
            'invoiceno' => 'required',
            'base_amount' => 'required',
            'discount_amount' => ['required', 'numeric', 'regex:/^\d+(\.\d+)?$/'],
            'invoice_amount' => 'nullable',
            'apfee' => 'nullable',
            'description' => 'nullable',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
            'invoiceno.required' => ResponseCodeEnum::required,
            'base_amount.required' => ResponseCodeEnum::required,
            'discount_amount.required' => ResponseCodeEnum::required,
            'discount_amount.regex' => "RC000042"
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
