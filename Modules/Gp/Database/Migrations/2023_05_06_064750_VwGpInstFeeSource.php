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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_FEE_SOURCE");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_FEE_SOURCE AS
                        SELECT S.*,
                            DIC.NAME,
                            DIC.NAME2,
                            DIC.VALUE
                        FROM GP_INST_FEE_SOURCE S
                        LEFT JOIN VW_DICT_GP_CONST_049 DIC ON DIC.VALUE = S.SOURCECODE
                        WHERE S.STATUSID = 1");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_FEE_SOURCE");
    }
};
