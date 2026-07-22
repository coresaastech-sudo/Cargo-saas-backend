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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_TXN_FEE_DETAIL");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_TXN_FEE_DETAIL AS
                        SELECT  TX.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                MS.NAME AS FEECODE_NAME,
                                MS.NAME2 AS FEECODE_NAME2
                        FROM GP_INST_TXN_FEE TX
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = TX.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = TX.UPDATED_BY
                        LEFT JOIN GP_INST_FEE MS ON MS.FEECODE = TX.FEECODE AND MS.INSTID = TX.INSTID");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_TXN_FEE_DETAIL");
    }
};
