<?php

namespace Modules\Ap\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApBonumCreateCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fullname' => 'required|string',
            'creditLimit' => 'required|numeric',
            'cardplanid' => 'required|string',
            'basicsupindicator' => 'required|string',
            'embossindicator' => 'required|string',
            'profile.gender' => 'required|string',
            'profile.identityNumber' => 'required|string',
            'profile.birthdate' => 'required|date_format:Ymd',
            'profile.mobile' => 'required|string',
            'profile.email' => 'required|email',
            'profile.nationality' => 'required|string',
            'address.addressLine1' => 'required|string',
            'address.addressLine2' => 'nullable|string',
            'address.addressLine3' => 'nullable|string',
            'address.state' => 'required|string',
            'address.city' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'fullname.required' => ResponseCodeEnum::required,
            'creditLimit.required' => ResponseCodeEnum::required,
            'cardplanid.required' => ResponseCodeEnum::required,
            'basicsupindicator.required' => ResponseCodeEnum::required,
            'embossindicator.required' => ResponseCodeEnum::required,
            'profile.gender.required' => ResponseCodeEnum::required,
            'profile.fullname.required' => ResponseCodeEnum::required,
            'profile.identityNumber.required' => ResponseCodeEnum::required,
            'profile.birthdate.required' => ResponseCodeEnum::required,
            'profile.birthdate.date_format' => ResponseCodeEnum::required,
            'profile.mobile.required' => ResponseCodeEnum::required,
            'profile.email.required' => ResponseCodeEnum::required,
            'profile.email.email' => ResponseCodeEnum::email,
            'profile.nationality.required' => ResponseCodeEnum::required,
            'address.addressLine1.required' => ResponseCodeEnum::required,
            'address.state.required' => ResponseCodeEnum::required,
            'address.city.required' => ResponseCodeEnum::required,
        ];
    }
}