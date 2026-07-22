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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_ADD");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_GP_INST_ADD AS
                        SELECT
                                CC.*,
                                GP.CODE
                            FROM GP_INST_ADD CC
                                JOIN GP_INST_ADD_FIELD GP ON GP.ID = CC.KEYFIELD AND GP.STATUSID = 1"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_ADD");
    }
};
