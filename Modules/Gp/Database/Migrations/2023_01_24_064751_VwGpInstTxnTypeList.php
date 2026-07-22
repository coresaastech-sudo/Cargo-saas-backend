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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_TXN_TYPE_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_TXN_TYPE_LIST AS
                        SELECT  TX.INSTID,
                                TX.MODULEID,
                                TX.ACTION_CODE,
                                TX.NAME,
                                TX.NAME2,
                                TX.STATUSID,
                                MS.MODULEID || ' - ' || MS.NAME AS MODULEID_NAME,
                                MS.NAME AS MODULE_NAME
                        FROM GP_INST_TXN_TYPE TX
                        LEFT JOIN GP_MODULE_LIST MS ON UPPER(MS.MODULEID) = UPPER(TX.MODULEID)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_TXN_TYPE_LIST");
    }
};
