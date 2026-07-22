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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_ADD");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_CR_CUST_ADD AS
                        SELECT
                                CC.*,
                                GP.CODE
                            FROM CR_CUST_ADD CC
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
        DB::statement("DROP VIEW VW_CR_CUST_ADD");
    }
};
