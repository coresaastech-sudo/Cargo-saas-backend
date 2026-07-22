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
        DB::statement("DROP VIEW IF EXISTS VW_AP_CUST_BANK_TOKEN");
        DB::statement("CREATE OR REPLACE VIEW VW_AP_CUST_BANK_TOKEN AS
                    SELECT
                        ACBT.*,
                        DIC.NAME AS BANK_NAME,
                        DIC.NAME2 AS BANK_NAME2,
                        DIC.VALUE_ADD1 AS DICVALUE1,
                        DIC.VALUE_ADD2 AS DICVALUE2
                    FROM
                        AP_CUST_BANK_TOKEN ACBT
                        LEFT JOIN GP_CONST DIC ON DIC.PARENT_CODE = 'negdi_bank_types'  AND DIC.VALUE = ACBT.BANKNAME AND DIC.STATUSID = 1
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_CUST_BANK_TOKEN");
    }
};
