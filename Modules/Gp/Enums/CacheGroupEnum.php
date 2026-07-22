<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class CacheGroupEnum extends Enum
{
    const core = 'CORE';
    const ln_account_type = 'LN_ACCOUNT_TYPE';
    const dp_account_type = 'DP_ACCOUNT_TYPE';
    const ia_account_type = 'IA_ACCOUNT_TYPE';
    const ia_ct_account_type = 'IA_CT_ACCOUNT_TYPE';
    const txntype = 'TXNTYPE';
    const gp_user_role = 'GP_USER_ROLE';
    const user_role = 'USER_ROLE';
    const GP_inst = 'GP_INST';
    const GP_inst_gp = 'GP_INST_GP';
    const GP_inst_cur = 'GP_INST_CUR';
    const GP_inst_susp = 'GP_INST_SUSP';
    const GP_inst_txn_type = 'GP_INST_TXN_TYPE';
    const GP_inst_txn_fee = 'GP_INST_TXN_FEE';
    const GP_inst_add_field = 'GP_INST_ADD_FIELD';
    const GP_inst_doc_temp = 'GP_INST_DOC_TEMP';
    const GP_inst_cur_rate = 'GP_INST_CUR_RATE';
    const GP_inst_fee = 'GP_INST_FEE';
    const GP_inst_fee_cur = 'GP_INST_FEE_CUR';
}
