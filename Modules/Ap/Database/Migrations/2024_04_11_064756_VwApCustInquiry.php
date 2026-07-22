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
        DB::statement("DROP VIEW IF EXISTS VW_AP_CUST_INQUIRY");
        DB::statement("CREATE OR REPLACE VIEW VW_AP_CUST_INQUIRY AS
                    SELECT
                        INQ.*,
                        DIC.NAME AS PROD_NAME,
                        U.LASTNAME || ' ' || U.FIRSTNAME AS CUST_NAME,
                        DIC1.NAME AS PURPTYPE_NAME,
                        Q.INVOICE_ID AS INVOICE_ID
                    FROM
                        AP_CUST_INQUIRY INQ
                        LEFT JOIN VW_DICT_GP_CONST_080 DIC ON DIC.VALUE = INQ.PRODUCTNO
                        LEFT JOIN VW_DICT_GP_CONST_081 DIC1 ON DIC1.VALUE = INQ.PURPTYPEID
                        LEFT JOIN AP_CUST_USER U ON U.ID = INQ.USERID
                        LEFT JOIN AP_QPAY Q ON CAST(Q.INQUIRY_ID AS BIGINT) = INQ.ID
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_CUST_INQUIRY");
    }
};
