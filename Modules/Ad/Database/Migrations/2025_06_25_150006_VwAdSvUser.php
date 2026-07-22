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
        DB::statement("DROP VIEW IF EXISTS VW_AD_SV_USER");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_SV_USER AS
         SELECT
            CC.*,
            CASE WHEN CC.SVTYPE = 1 THEN 'Онлайн'
                ELSE 'Офлайн'
            END AS SVTYPE_NAME,
            GU0.NAME AS USER_NAME,
            GU3.NAME AS SVUSER_NAME,
            GU0.ID || ' - ' || GU0.NAME AS USERID_NAME,
            GU3.ID || ' - ' || GU3.NAME AS SVUSERID_NAME,
            GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
            GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
        FROM
            AD_SV_USER CC
            LEFT JOIN GP_INST_USER GU0 ON GU0.ID = CC.USERID
            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
            LEFT JOIN GP_INST_USER GU3 ON GU3.ID = CC.SVUSERID
            "
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_SV_USER");
    }
};
