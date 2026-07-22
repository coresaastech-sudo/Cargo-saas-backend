<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class ResponseCodeEnum extends Enum {
    const success = 'RC000000';
    const sys_error = "RC000003";
    const required = "VC000008";
    const email = "VC000023";
    const array = "VC000008";
    const numeric = "VC000018";
    const date = "VC000019";
    const max = "VC000022";
}
