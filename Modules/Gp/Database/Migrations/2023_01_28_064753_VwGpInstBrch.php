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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_BRCH");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_BRCH AS
                        SELECT BR.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM GP_INST_BRANCH BR
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = BR.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = BR.UPDATED_BY");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_BRCH");
    }
};
