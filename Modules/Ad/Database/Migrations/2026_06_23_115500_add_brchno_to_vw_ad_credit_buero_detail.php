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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_DETAIL");

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_DETAIL AS
                    SELECT 
                        CR.ID1,
                        LN.NAME,
                        BR.BRCHNO,
                        BR.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                        CB.*
                    FROM
                    AD_CREDIT_INFO_BUERO_DETAIL CB
                    LEFT JOIN VW_CR_CUST_LISTS CR ON CR.CUSTNO = CB.CUSTNO
                        AND CR.INSTID = CB.INSTID
                    JOIN LN_ACCOUNT LN ON LN.ACNTNO = CB.ACNTNO
                        AND LN.INSTID = CB.INSTID AND LN.SENDCREDITBUREO = 1
                    LEFT JOIN GP_INST_BRANCH BR ON BR.BRCHNO = LN.BRCHNO AND BR.INSTID = LN.INSTID"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_DETAIL");

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_DETAIL AS
                    SELECT 
                        CR.ID1,
                        LN.NAME,
                        CB.*
                    FROM
                    AD_CREDIT_INFO_BUERO_DETAIL CB
                    LEFT JOIN VW_CR_CUST_LISTS CR ON CR.CUSTNO = CB.CUSTNO
                        AND CR.INSTID = CB.INSTID
                    JOIN LN_ACCOUNT LN ON LN.ACNTNO = CB.ACNTNO
                        AND LN.INSTID = CB.INSTID AND LN.SENDCREDITBUREO = 1"
        );
    }
};
