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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_061");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_061 AS
                        SELECT ID,
                            id || ' - ' || NAME as NAME,
                            NAME2,
                            id || '' AS VALUE,
                            id AS LISTORDER,
                            INSTID
                        FROM GP_CONN_CONF
                        WHERE STATUSID = 1
                        ORDER BY ID");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_061");
    }
};
