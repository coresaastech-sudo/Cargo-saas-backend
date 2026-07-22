<?php

namespace Modules\Ap\Enums;

use App\Enum\Enum;

class ApAccountTypeEnum extends Enum {
    const cca = 'CCA_ACNT';
    const cca_acnt = 'CCA_ACNT';
    const sa = 'CASA_ACNT';
    const casa_acnt = 'CASA_ACNT';
    const ca = 'CASA_ACNT';
    const loan = 'LOAN_ACNT';
    const loan_acnt = 'LOAN_ACNT';
    const ln = 'LOAN_ACNT';
    const commitment = 'LOAN_ACNT';
    const line_cust = 'LOAN_ACNT';
    const line = 'LINE_ACNT';
    const td = 'TD_ACNT';
    const td_acnt = 'TD_ACNT';
    const dp = 'CASA_ACNT';

    /**
     * Override fromString method to return original string if not found
     *
     * @param string $name
     * @return string|false
     */
    public static function fromString($name)
    {
        if (self::isValidName($name, $strict = true)) {
            $constants = parent::getConstants();
            return $constants[$name];
        }

        // Return original string if enum value is not found
        return $name;
    }
}
