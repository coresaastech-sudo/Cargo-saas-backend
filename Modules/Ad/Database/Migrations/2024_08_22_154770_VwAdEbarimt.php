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
        DB::statement("DROP VIEW IF EXISTS VW_AD_EBARIMT");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_EBARIMT AS
         SELECT
            CC.*,
            CC.TXNCODE || ' - ' || AC.NAME as PC_NAME,
            GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME
        FROM
            ad_ebarimt CC
            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
            LEFT JOIN GP_ACTION_CODE AC ON AC.ACTION_CODE = CC.TXNCODE"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_EBARIMT");
    }
};
