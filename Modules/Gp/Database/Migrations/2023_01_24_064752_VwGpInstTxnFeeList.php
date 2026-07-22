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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_TXN_FEE_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_TXN_FEE_LIST AS
                        SELECT  TX.ID,
                                TX.INSTID,
                                TX.ACTION_CODE,
                                TX.FEECODE,
                                MS.NAME AS FEECODE_NAME,
                                MS.NAME2 AS FEECODE_NAME2,
                                TX.STATUSID
                        FROM GP_INST_TXN_FEE TX
                        LEFT JOIN GP_INST_FEE MS ON MS.FEECODE = TX.FEECODE AND MS.INSTID = TX.INSTID");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_TXN_FEE_LIST");
    }
};
