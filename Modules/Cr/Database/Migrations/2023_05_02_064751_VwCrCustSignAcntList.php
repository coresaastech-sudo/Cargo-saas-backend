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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_SIGN_ACNT_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_SIGN_ACNT_LIST AS
                        SELECT
                                SA.ACNT_MODULE,
                                SA.ACNTNO,
                                SA.STATUSID,
                                SA.INSTID,
                                SI.NAME,
                                SI.NAME2,
                                SI.SIGN_LEVEL,
                                SI.IMAGE,
                                DIC.NAME AS SIGN_LEVELNAME
                            FROM CR_CUST_SIGN_ACNT SA
                                LEFT JOIN CR_CUST_SIGN SI ON SI.ID = SA.SIGNID AND SI.INSTID = SA.INSTID AND SI.CUSTID = SA.CUSTID AND SI.STATUSID=1
                                LEFT JOIN VW_DICT_GP_CONST_022 DIC ON DIC.VALUE = CAST(SA.SIGN_LEVEL AS VARCHAR)

        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_SIGN_ACNT_LIST");
    }
};
