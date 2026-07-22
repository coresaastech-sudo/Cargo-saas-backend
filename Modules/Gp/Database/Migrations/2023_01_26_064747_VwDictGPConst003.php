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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_003");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_003 AS
                        SELECT ID,
                            NAME,
                            NAME2,
                            CAST(ID AS VARCHAR) VALUE,
                            LISTORDER,
                            1 AS INSTID
                        FROM GP_INST_LIST
                        WHERE STATUSID = 1
                        ORDER BY NAME");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_003");
    }
};
