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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_CUR_RATE_SIDEBAR_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_CUR_RATE_SIDEBAR_LIST AS
                        SELECT CR.*,
                                DIC.NAME AS RTYPECODE_NAME,
                                CUR.NAME AS NAME,
                                CUR.NAME2 AS NAME2,
                                CUR.LISTORDER,
                                CUR.SHOWSIDEMENU,
                                CUR.SHOWONLINE
                        FROM GP_INST_CUR_RATE CR
                        LEFT JOIN GP_INST_CUR CUR ON CUR.CURCODE = CR.CURCODE AND CUR.INSTID = CR.INSTID AND CUR.STATUSID = 1
                        LEFT JOIN VW_DICT_GP_CONST_031 DIC ON DIC.VALUE = CR.RTYPECODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_CUR_RATE_SIDEBAR_LIST");
    }
};
