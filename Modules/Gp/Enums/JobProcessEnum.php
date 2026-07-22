<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class JobProcessEnum extends Enum {
    const pending = 0;
    const finished = 1;
    const processing = 2;
    const stopped = 3;
}
