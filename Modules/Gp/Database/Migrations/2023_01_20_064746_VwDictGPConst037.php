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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_037");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_037 AS
                        SELECT ID,
                        FEECODE || ' - ' || NAME AS NAME,
                            NAME2,
                            FEECODE AS VALUE,
                            LISTORDER,
                            INSTID
                        FROM GP_INST_FEE
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
        DB::statement("DROP VIEW VW_DICT_GP_CONST_037");
    }
};
