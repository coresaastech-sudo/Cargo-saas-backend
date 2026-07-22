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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_103");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_103 AS
                        SELECT ID,
                            ID || ' - ' || ROLENAME AS NAME,
                            ROLENAME2 AS NAME2,
                            ID VALUE,
                            LISTORDER,
                            INSTID PARENT_CODE,
                            INSTID
                        FROM GP_INST_ROLE
                        WHERE STATUSID = 1 AND ISADMIN::INT = 0
                        ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_103");
    }
};
