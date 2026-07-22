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
        DB::statement("DROP VIEW IF EXISTS VW_RE_INST_REPORT_TEMP");
        DB::statement("CREATE OR REPLACE VIEW VW_RE_INST_REPORT_TEMP AS
                        SELECT
                                R.*,
                                R.GROUPID || ' - ' || DIC.NAME AS GROUPID_NAME,
                                R.GROUPID || ' - ' || DIC.NAME2 AS GROUPID_NAME2,
                                DIM.NAME AS DIMENSIONNAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM RE_INST_REPORT_TEMP R
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = R.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = R.UPDATED_BY
                        LEFT JOIN RE_INST_REPORT_TEMP_DIM DIM ON DIM.ID = R.DIMENSIONID AND DIM.INSTID = R.INSTID AND DIM.STATUSID = R.STATUSID
                        LEFT JOIN GP_CONST DIC ON DIC.PARENT_CODE = 'report_groupid' AND DIC.VALUE = R.GROUPID
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_RE_INST_REPORT_TEMP");
    }
};
