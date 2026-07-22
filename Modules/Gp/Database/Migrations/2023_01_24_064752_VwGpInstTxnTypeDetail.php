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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_TXN_TYPE_DETAIL");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_TXN_TYPE_DETAIL AS
                        SELECT  TX.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                IA1.ACNTNO || ' - ' || IA1.NAME AS ACNTNO1_NAME,
                                IA1.NAME AS ACNT1_NAME,
                                IA2.ACNTNO || ' - ' || IA2.NAME AS ACNTNO2_NAME,
                                IA2.NAME AS ACNT2_NAME,
                                MS.MODULEID || ' - ' || MS.NAME AS MODULEID_NAME,
                                MS.NAME AS MODULE_NAME
                        FROM GP_INST_TXN_TYPE TX
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = TX.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = TX.UPDATED_BY
                        LEFT JOIN IA_ACCOUNT IA1 ON IA1.ACNTNO = TX.ACNTNO1 AND IA1.INSTID=TX.INSTID
                        LEFT JOIN IA_ACCOUNT IA2 ON IA2.ACNTNO = TX.ACNTNO2 AND IA1.INSTID=TX.INSTID
                        LEFT JOIN GP_MODULE_LIST MS ON UPPER(MS.MODULEID) = UPPER(TX.MODULEID)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_TXN_TYPE_DETAIL");
    }
};
