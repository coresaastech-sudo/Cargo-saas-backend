<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class HoldInitTypeCodeEnum extends Enum {
    const byholdtran = 1;
    const bypendtran = 2;
    const byreviewtran = 3;
    const bypreauthorization = 4;
    const byeodtran = 5;
}
