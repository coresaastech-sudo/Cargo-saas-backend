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
        DB::statement("DROP VIEW IF EXISTS VW_INST_ROLE_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_INST_ROLE_LIST AS
                        SELECT U.*,
                        IL.NAME as INST_NAME,
                        IL.NAME2 as INST_NAME2
                        FROM GP_INST_ROLE U
                        LEFT JOIN GP_INST_LIST IL ON IL.ID = U.instid
                        WHERE U.STATUSID = 1");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_INST_ROLE_LIST");
    }
};
