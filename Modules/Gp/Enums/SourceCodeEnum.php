<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class SourceCodeEnum extends Enum
{
    const CORE = 1; // Кор системээр бүртгэсэн хэрэглэгч
    const APP = 2;  // Аппаас өөрөө бүртгүүлсэн хэрэглэгч
}
