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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_092");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_092 AS
                        SELECT ID,
                            VALUE  || ' - ' || NAME as NAME,
                            NAME2,
                            ID VALUE,
                            LISTORDER,
                            INSTID
                        from GP_CONST where parent_code in (
                            SELECT code
                            FROM GP_CONST
                            WHERE PARENT_CODE = 'lntype' AND IS_SHOW_FRONT = 1
                        ) and statusid = 1 and IS_SHOW_FRONT = 1 ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_092");
    }
};
