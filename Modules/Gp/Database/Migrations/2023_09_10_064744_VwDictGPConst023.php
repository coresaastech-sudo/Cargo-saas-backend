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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_023");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_023 AS
                        SELECT BRCHNO ID,
                            BRCHNO || ' - ' || NAME as NAME,
                            NAME2,
                            BRCHNO VALUE,
                            LISTORDER,
                            INSTID PARENT_CODE,
                            INSTID
                        FROM GP_INST_BRANCH
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
        DB::statement("DROP VIEW VW_DICT_GP_CONST_023");
    }
};
