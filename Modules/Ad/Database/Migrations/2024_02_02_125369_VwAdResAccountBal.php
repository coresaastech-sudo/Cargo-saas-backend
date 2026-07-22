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
        DB::statement("DROP VIEW IF EXISTS VW_AD_RES_ACCOUNT_BAL");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_RES_ACCOUNT_BAL AS
                        SELECT RS.*,
                            RS.UPDATED_BY || ' - ' || GU1.NAME AS UPDATED_BY_NAME,
                            RS.CLSCODE || ' - ' || DM.NAME AS CLSCODE_NAME,
                            COALESCE (RS.RESCLS, 1) || ' - ' || DM1.NAME AS RESCLS_NAME
                        FROM AD_RES_ACCOUNT_BAL RS
                            LEFT JOIN GP_CONST DM
                                ON DM.PARENT_CODE = 'clscode' AND DM.VALUE::INT = RS.CLSCODE
                            LEFT JOIN GP_CONST DM1
                                ON DM1.PARENT_CODE = 'clscode' AND DM1.VALUE::INT = COALESCE (RS.RESCLS, 1)
                            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = RS.UPDATED_BY WHERE RS.statusid > -1

       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_RES_ACCOUNT_BAL");
    }
};
