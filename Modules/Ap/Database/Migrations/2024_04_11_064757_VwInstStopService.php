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
        DB::statement("DROP VIEW IF EXISTS VW_AP_INST_STOP_SERVICE");
        DB::statement("CREATE OR REPLACE VIEW VW_AP_INST_STOP_SERVICE AS
                        SELECT  S.*,
                                DIC1.VALUE || ' - ' || DIC1.NAME AS PROD_CODE_NAME,
                                DIC1.VALUE || ' - ' || DIC2.NAME AS OPERATION_NAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM AP_INST_STOP_SERVICE S
                        LEFT JOIN GP_CONST DIC1 ON DIC1.PARENT_CODE IN
                        (
                            SELECT CODE FROM GP_CONST WHERE PARENT_CODE  = 'PRODUCTS'
                        ) AND DIC1.VALUE = S.PROD_CODE
                        LEFT JOIN GP_CONST DIC2 ON DIC2.PARENT_CODE = 'OPERATION' AND DIC2.VALUE = S.OPERATION
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = S.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = S.UPDATED_BY
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_INST_STOP_SERVICE");
    }
};
