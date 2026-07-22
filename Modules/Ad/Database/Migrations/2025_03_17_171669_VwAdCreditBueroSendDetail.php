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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_SEND_DETAIL");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_SEND_DETAIL AS
                    SELECT
                LAC.*,
                LAP.APPROVEDATE AS CONTRACTDATE,
                LAT.LNTYPE,
                LAT.REDRAW,
                TXN.POSTDATE,
                TXN.TXNDATE
            FROM
                LN_ACCOUNT LAC
                LEFT JOIN LN_APP LAP ON LAP.APPNO = LAC.APPNO
                LEFT JOIN LN_ACCOUNT_TYPE LAT ON LAT.PRODCODE = LAC.PRODCODE
                LEFT JOIN (WITH FIRSTTXN AS (
                        SELECT
                            TXN.ACNTNO,
                            TXN.POSTDATE,
                            TXN.TXNDATE,
                            ROW_NUMBER() OVER (PARTITION BY TXN.ACNTNO ORDER BY TXN.TXNDATE ASC) AS RN
                        FROM
                            LN_TXN TXN
                            ORDER BY TXN.TXNDATE ASC
            )
                    SELECT
                        ACNTNO,
                        POSTDATE,
                        TXNDATE
                    FROM
                        FIRSTTXN
                    WHERE
                        RN = 1
            ) TXN ON TXN.ACNTNO = LAC.ACNTNO
            WHERE
                LAC.SENDCREDITBUREO = 1
                AND LAC.STATUSID NOT IN(-1, 5)
                AND LAT.INSTID = LAC.INSTID"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_EBARIMT");
    }
};
