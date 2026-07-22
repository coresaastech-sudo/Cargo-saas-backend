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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_104");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_104 AS
                        SELECT ROW_NUMBER() OVER () AS ID,
                            ACTION_CODE || ' - ' || NAME AS NAME,
                            ACTION_CODE || ' - ' || NAME2 AS NAME2,
                            ACTION_CODE VALUE,
                            ROW_NUMBER() OVER () AS LISTORDER,
                            1 PARENT_CODE,
                            1 INSTID
                        FROM GP_ACTION_CODE
                        WHERE TXNTYPE IN (2, 5)
                        ORDER BY ACTION_CODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_104");
    }
};
