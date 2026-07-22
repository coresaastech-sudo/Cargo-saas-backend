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
        // DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_081");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_081 AS
                        SELECT ID,
                            NAME,
                            NAME2,
                            VALUE,
                            LISTORDER,
                            INSTID
                        FROM GP_CONST
                        WHERE PARENT_CODE = 'ME_APP_ZMS_PURP_TYPES' AND IS_SHOW_FRONT = 1
                        ORDER BY LISTORDER");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_081");
    }
};
