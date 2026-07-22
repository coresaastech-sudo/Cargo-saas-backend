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
        DB::statement("DROP VIEW IF EXISTS VW_AD_REC_RES_ACCOUNT_BAL_CALC");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_REC_RES_ACCOUNT_BAL_CALC AS
                        SELECT RP.INSTID,
                            RP.RECPAYNO,
                            RP.BALANCE AS RECBALANCE,
                            RP.CURCODE,
                            RP.CLSCODE,
                            RP.CLSCODE || ' - ' || DM.NAME AS CLSCODE_NAME,
                            DM.VALUE_ADD2::INT AS PER,
                            ROUND((RP.BALANCE * DM.VALUE_ADD2::INT / 100) ,2) AS NEWRESBAL,
                            COALESCE (RS.ACNTTYPE, 'R') AS RESACNTTYPE,
                            RS.RESDATE,
                            COALESCE (RS.BALANCE, 0) AS BALANCE,
                            COALESCE (RS.CLSCODE, 1) AS RESCLS,
                            COALESCE (RS.CLSCODE, 1) || ' - ' || DM1.NAME AS RESCLS_NAME,
                            COALESCE (RS.RESBAL, 0) AS RESBAL,
                            ROUND((ROUND(RP.BALANCE * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) AS AMOUNT,
                            CASE
                                WHEN ROUND((ROUND(RP.BALANCE * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTTYPE
                                ELSE SINC.ACNTTYPE
                            END AS CONT_ACNTTYPE,
                            CASE
                                WHEN ROUND((ROUND(RP.BALANCE * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTNO
                                ELSE SINC.ACNTNO
                            END AS CONT_ACNTNO,
                            SRES.ACNTTYPE AS RES_ACNTTYPE,
                            SRES.ACNTNO AS RES_ACNTNO
                        FROM IA_REC_PAY RP
                            LEFT JOIN GP_CONST DM
                                ON DM.PARENT_CODE = 'clscode' AND DM.VALUE::INT = RP.CLSCODE
                            LEFT JOIN (
                                select * from AD_RES_ACCOUNT_BAL where id in (
                                    select max(id) from AD_RES_ACCOUNT_BAL where STATUSID = 1 and ACNTTYPE = 'R' group by ACNTNO, STATUSID, INSTID
                                )
                            )  RS
                                ON RS.INSTID = RP.INSTID AND RS.ACNTNO = RP.RECPAYNO::TEXT
                            LEFT JOIN GP_CONST DM1
                                ON DM1.PARENT_CODE = 'clscode' AND DM1.VALUE::INT = COALESCE (RS.CLSCODE, 1)
                            LEFT JOIN GP_INST_SUSP SEXP
                                ON SEXP.INSTID = RP.INSTID
                                    AND SEXP.ACNTCODE = 'RECRESEXP'
                                    AND SEXP.STATUSID = 1
                                    AND SEXP.BRCHNO = RP.BRCHNO
                                    AND SEXP.CURCODE = RP.CURCODE
                            LEFT JOIN GP_INST_SUSP SINC
                                ON SINC.INSTID = RP.INSTID
                                    AND SINC.ACNTCODE = 'RECRESINC'
                                    AND SINC.STATUSID = 1
                                    AND SINC.BRCHNO = RP.BRCHNO
                                    AND SINC.CURCODE = RP.CURCODE
                            LEFT JOIN GP_INST_SUSP SRES
                                ON SRES.INSTID = RP.INSTID
                                    AND SRES.ACNTCODE = 'RECRES'
                                    AND SRES.STATUSID = 1
                                    AND SRES.BRCHNO = RP.BRCHNO
                                    AND SRES.CURCODE = RP.CURCODE
                        WHERE RP.CLSCODE >= 1
                            AND ABS(ROUND(ROUND(RP.BALANCE * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0), 2)) > 0.01
       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_REC_RES_ACCOUNT_BAL_CALC");
    }
};
