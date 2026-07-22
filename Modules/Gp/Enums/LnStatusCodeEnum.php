<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class LnStatusCodeEnum extends Enum {
    const deleted = -1;
    const closed = 0;
    const approved = 1;
    // Зөвхөн төлөлтийн гүйлгээ хийгдэнэ
    const stop = 2;
    // Зөвхөн төлөлтийн гүйлгээ хийгдэнэ
    const stopint = 3;
    // Олголт төлөлт хийгдэнэ
    const issued = 4;
    // None
    const new = 5;
    // Төлөлт хийгдэнэ
    const sold = 8;
    // none
    const soldclosed = 9;
}
