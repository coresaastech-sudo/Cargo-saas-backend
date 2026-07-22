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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_032");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_032 AS
                        SELECT ID,
                            CURCODE AS NAME,
                            NAME AS NAME1,
                            NAME2,
                            CURCODE AS VALUE,
                            CURCODE,
                            INSTID,
                            LISTORDER
                            FROM GP_INST_CUR
                            WHERE STATUSID = 1
                            ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_032");
    }
};
