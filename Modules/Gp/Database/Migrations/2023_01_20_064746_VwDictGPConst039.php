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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_039");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_039 AS
                    SELECT
                        ROW_NUMBER() OVER () AS ID,
                        ML.MODULEID AS VALUE,
                        ML.MODULEID || ' - ' || ML.NAME AS NAME,
                        ML.NAME2,
                        ML.LISTORDER,
                        1 AS INSTID
                    FROM GP_MODULE_LIST ML
                        WHERE ML.PARENTID IS NULL
                        AND ML.TYPEID = '1'
                        ORDER BY ML.LISTORDER");
                        }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_039");
    }
};
