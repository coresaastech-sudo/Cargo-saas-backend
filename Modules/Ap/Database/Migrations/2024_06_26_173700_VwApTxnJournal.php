<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AP_TXN_JOURNAL");
        DB::statement("CREATE OR REPLACE VIEW VW_AP_TXN_JOURNAL AS
                        SELECT
                            JR.ID,
                            JR.INSTID,
                            INS.NAME AS INST_NAME,
                            JR.USERID,
                            JR.TXN_ACNT_CODE,
                            JR.TXN_DATE,
                            JR.TXN_AMOUNT,
                            JR.CUR_CODE,
                            JR.TXN_JRNO,
                            JR.TXN_CORR_JRNO,
                            JR.TXN_DESC,
                            JR.TXN_TYPE,
                            JR.IDENTITY_TYPE,
                            JR.CONT_ACNT_CODE,
                            JR.CONT_AMOUNT,
                            JR.CONT_BANK_CODE,
                            JR.CONT_CUR_CODE,
                            JR.CONT_RATE,
                            JR.CORE_CORR_JRNO,
                            JR.CORE_JRNO,
                            JR.ERR_DESC,
                            JR.FEE_ID,
                            JR.FEE_INST_AMOUNT,
                            JR.FEE_SYS_AMOUNT,
                            JR.INTERNAL_CONT_ACNT_CODE,
                            JR.IS_PREVIEW,
                            JR.IS_PREVIEW_FEE,
                            JR.IS_SUPERVISOR,
                            JR.IS_TMW,
                            JR.JR_ITEM_NO_AND_INCR,
                            JR.OPER_CODE,
                            JR.PARENT_JRNO,
                            JR.RATE,
                            JR.SOURCE_TYPE,
                            JR.TCUST_ADDR,
                            JR.TCUST_CONTACT,
                            JR.TCUST_NAME,
                            JR.TCUST_REGISTER,
                            JR.TCUST_REGISTER_MASK,
                            JR.STATUSID,
                            JR.CREATED_BY,
                            JR.UPDATED_BY,
                            JR.CREATED_AT,
                            JR.UPDATED_AT,
                            JR.PRODCODE
                        FROM
                            AP_TXN_JOURNAL JR
                        LEFT JOIN
                            GP_INST_LIST INS ON INS.ID = JR.INSTID
                    ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_TXN_JOURNAL");
    }
};
