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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_062");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_062 AS
                        SELECT
                            REGEXP_REPLACE(acntno, '\D', '', 'g')::BIGINT AS ID,
                            acntno || ' - ' || NAME as NAME,
                            NAME2,
                            acntno || '' AS VALUE,
                            LISTORDER,
                            INSTID
                        FROM GL_ACCOUNT
                        WHERE STATUSID = 1
                        ORDER BY listorder");
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
