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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_076");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_076 AS
                        SELECT
                            ROW_NUMBER() OVER () AS ID,
                            TYPECODE || ' - ' || NAME as NAME,
                            NAME2,
                            TYPECODE || '' AS VALUE,
                            LISTORDER,
                            INSTID
                        FROM IA_CT_ACCOUNT_TYPE
                        ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_076");
    }
};
