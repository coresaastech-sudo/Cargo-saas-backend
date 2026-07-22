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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_048");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_048 AS
                        SELECT      REGEXP_REPLACE(ACTION_CODE, '\D', '', 'g')::BIGINT AS ID,
                                    ACTION_CODE AS VALUE,
                                    ACTION_CODE || ' - ' || NAME AS NAME,
                                    NAME2,
                                    1 LISTORDER,
                                    STATUSID,
                                    INSTID
                            FROM GP_INST_TXN_TYPE
                            WHERE UPPER(MODULEID) = 'TR'
                            ORDER BY ACTION_CODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_048");
    }
};
