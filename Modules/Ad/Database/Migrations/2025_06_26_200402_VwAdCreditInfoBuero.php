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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_INFO_BUERO");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_INFO_BUERO AS
        SELECT CC.*,
        GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
        CL.CUSTNO || ' - ' || CL.NAME AS CUST_NAME,
        CL.NAME AS CUST
    FROM AD_CREDIT_INFO_BUERO CC
        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
        LEFT JOIN VW_CR_CUST_LISTS CL ON CL.CUSTNO = CC.CUSTNO AND CL.INSTID = CC.INSTID;"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_CREDIT_INFO_BUERO");
    }
};
