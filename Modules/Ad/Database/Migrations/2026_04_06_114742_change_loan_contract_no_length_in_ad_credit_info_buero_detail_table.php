<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_DETAIL");

        Schema::table('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->string('loan_contract_no', 100)->change();
        });

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_DETAIL AS
                    SELECT 
                        CR.ID1,
                        LN.NAME,
                        CB.*
                    FROM
                    AD_CREDIT_INFO_BUERO_DETAIL CB
                    LEFT JOIN VW_CR_CUST_LISTS CR ON CR.CUSTNO = CB.CUSTNO
                        AND CR.INSTID = CB.INSTID
                    JOIN LN_ACCOUNT LN ON LN.ACNTNO = CB.ACNTNO
                        AND LN.INSTID = CB.INSTID AND LN.SENDCREDITBUREO = 1"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_DETAIL");

        Schema::table('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->string('loan_contract_no', 20)->change();
        });

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_DETAIL AS
                    SELECT 
                        CR.ID1,
                        LN.NAME,
                        CB.*
                    FROM
                    AD_CREDIT_INFO_BUERO_DETAIL CB
                    LEFT JOIN VW_CR_CUST_LISTS CR ON CR.CUSTNO = CB.CUSTNO
                        AND CR.INSTID = CB.INSTID
                    JOIN LN_ACCOUNT LN ON LN.ACNTNO = CB.ACNTNO
                        AND LN.INSTID = CB.INSTID AND LN.SENDCREDITBUREO = 1"
        );
    }
};
