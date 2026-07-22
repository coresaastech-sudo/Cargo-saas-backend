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
        DB::statement("DROP VIEW IF EXISTS VW_AD_NOTIFICATIONS");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_NOTIFICATIONS AS
                SELECT CC.*,
                RE.NAME AS temp_name,
                AU.NAME AS autojob_name,
                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME
            FROM AD_NOTIFICATIONS CC
                LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                LEFT JOIN re_inst_report_temp RE ON RE.ACTION_CODE = CC.reportActionCode
                LEFT JOIN ad_auto_job AU ON AU.ID = CC.autojobid"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_NOTIFICATIONS");
    }
};
