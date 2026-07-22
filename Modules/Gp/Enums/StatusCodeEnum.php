<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class StatusCodeEnum extends Enum {
    const active = 1;
    const deactive = 0;
    const deleted = -1;
}
