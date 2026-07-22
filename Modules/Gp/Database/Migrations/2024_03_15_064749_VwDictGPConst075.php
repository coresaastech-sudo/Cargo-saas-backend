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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_075");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_075 AS
                        SELECT ROW_NUMBER() OVER () AS ID,
                            TYPECODE || ' - ' || NAME as NAME,
                            NAME2,
                            TYPECODE || '' AS VALUE,
                            LISTORDER,
                            INSTID AS PARENT_CODE,
                            INSTID
                        FROM IA_ACCOUNT_TYPE
                        ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_075");
    }
};
