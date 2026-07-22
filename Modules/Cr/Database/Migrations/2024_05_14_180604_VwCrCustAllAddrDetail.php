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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_All_ADDR_DETAIL");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_All_ADDR_DETAIL AS
                        SELECT
                            ADDR.ID,
                            ADDR.CUSTID,
                            ADDR.CUSTTYPECODE,
                            ADDR.INSTID,
                            ADDR.STATUSID,
                            ADDR.ADDRTYPECODE,
                            ADDR.ADDRTYPECODE || ' - ' || TYPE.NAME AS ADDRTYPE_NAME,
                            CONCAT_WS(', ',
                                NULLIF(CITY.NAME, ''),
                                NULLIF(REGION.NAME, ''),
                                NULLIF(SUBREGION.NAME, ''),
                                NULLIF(ADDR.ADDRESS, '')
                            ) AS ADDRESS
                        FROM CR_CUST_ADDRESS ADDR
                        LEFT JOIN GP_CONST TYPE ON TYPE.PARENT_CODE = 'addr_type' AND TYPE.VALUE::INT = ADDR.ADDRTYPECODE
                        LEFT JOIN GP_CONST CITY ON CITY.PARENT_CODE = 'city' AND CITY.VALUE::INT = ADDR.STATE
                        LEFT JOIN GP_CONST REGION ON REGION.VALUE_ADD1 = CITY.VALUE AND REGION.VALUE = ADDR.REGION::TEXT
                        LEFT JOIN GP_CONST SUBREGION ON SUBREGION.VALUE_ADD1 = REGION.VALUE AND SUBREGION.VALUE = ADDR.SUBREGION
    ");
        }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_All_ADDR_DETAIL");
    }
};
