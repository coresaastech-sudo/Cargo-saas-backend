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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_INVOICE");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_GP_INST_INVOICE AS
                        SELECT
                                CC.*,
                                CC.INSTID || ' - ' || I.NAME AS INSTID_NAME,
                                CC.STARTDATE || ' - ' || CC.ENDDATE AS PERIOD,
                                CC.CREATED_AT AS CREATED_DATE,
                                CASE WHEN CC.STATUSID = 1 THEN 'ҮҮСГЭСЭН'
                                     WHEN CC.STATUSID = 2 THEN 'ДУТУУ'
                                     WHEN CC.STATUSID = 3 THEN 'ХЭТЭРСЭН'
                                     WHEN CC.STATUSID = 4 THEN 'ТӨЛСӨН'
                                     ELSE ''
                                END AS STATUS_NAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                COALESCE(CC.INVOICE_AMOUNT - CC.PAID_AMOUNT) AS CURRENTBAL
                            FROM GP_INST_INVOICE CC
                                JOIN GP_INST_LIST I ON I.ID = CC.INSTID
                                LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                                LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_INVOICE");
    }
};
