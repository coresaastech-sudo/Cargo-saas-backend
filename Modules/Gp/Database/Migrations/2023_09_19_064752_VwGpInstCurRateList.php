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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_CUR_RATE_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_CUR_RATE_LIST AS
                        SELECT CR.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                IC.LISTORDER,
                                IC.AVGRATE,
                                IC.AVGRATEEND,
                                IC.MIDRATE,
                                DIC.NAME AS RTYPECODE_NAME
                        FROM GP_INST_CUR_RATE CR
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CR.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CR.UPDATED_BY
                        LEFT jOIN GP_INST_CUR IC ON IC.CURCODE = CR.CURCODE AND IC.INSTID = CR.INSTID AND IC.STATUSID = 1
                        LEFT JOIN VW_DICT_GP_CONST_031 DIC ON DIC.VALUE = CR.RTYPECODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_CUR_RATE_LIST");
    }
};
