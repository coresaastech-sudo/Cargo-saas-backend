<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class AcntStatusCodeEnum extends Enum {
    const new = 1;
    const stop = 2;
    const open = 4;
    const dormant = 5;
    const closed = 0;
}
