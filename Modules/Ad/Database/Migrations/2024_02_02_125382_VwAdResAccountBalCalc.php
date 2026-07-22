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
        DB::statement("DROP VIEW IF EXISTS VW_AD_RES_ACCOUNT_BAL_CALC");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_RES_ACCOUNT_BAL_CALC AS
                        SELECT LN.INSTID,
                            LN.ACNTNO,
                            LN.PRINCBAL,
                            LN.CURCODE,
                            LN.CLSCODE,
                            LN.CLSCODE || ' - ' || DM.NAME AS CLSCODE_NAME,
                            DM.VALUE_ADD2::INT AS PER,
                            ROUND((LN.PRINCBAL * DM.VALUE_ADD2::INT / 100) ,2) AS NEWRESBAL,
                            COALESCE (RS.ACNTTYPE, 'LN') AS RESACNTTYPE,
                            RS.RESDATE,
                            COALESCE (RS.BALANCE, 0) AS BALANCE,
                            COALESCE (RS.CLSCODE, 1) AS RESCLS,
                            COALESCE (RS.CLSCODE, 1) || ' - ' || DM1.NAME AS RESCLS_NAME,
                            COALESCE (RS.RESBAL, 0) AS RESBAL,
                            ROUND((ROUND(LN.PRINCBAL * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) AS AMOUNT,
                            CASE
                                WHEN ROUND((ROUND(LN.PRINCBAL * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTTYPE
                                ELSE SINC.ACNTTYPE
                            END AS CONT_ACNTTYPE,
                            CASE
                                WHEN ROUND((ROUND(LN.PRINCBAL * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTNO
                                ELSE SINC.ACNTNO
                            END AS CONT_ACNTNO,
                            SRES.ACNTTYPE AS RES_ACNTTYPE,
                            SRES.ACNTNO AS RES_ACNTNO
                        FROM LN_ACCOUNT LN
                            LEFT JOIN GP_CONST DM
                                ON DM.PARENT_CODE = 'clscode' AND DM.VALUE::INT = LN.CLSCODE
                            LEFT JOIN (
                                select * from AD_RES_ACCOUNT_BAL where id in (
                                    select max(id) from AD_RES_ACCOUNT_BAL where STATUSID = 1 and ACNTTYPE = 'LN' group by ACNTNO, STATUSID, INSTID
                                )
                            )  RS
                                ON RS.INSTID = LN.INSTID AND RS.ACNTNO = LN.ACNTNO
                            LEFT JOIN GP_CONST DM1
                                ON DM1.PARENT_CODE = 'clscode' AND DM1.VALUE::INT = COALESCE (RS.CLSCODE, 1)
                            LEFT JOIN GP_INST_SUSP SEXP
                                ON SEXP.INSTID = LN.INSTID
                                    AND SEXP.ACNTCODE = 'LONRESEXP'
                                    AND SEXP.STATUSID = 1
                                    AND SEXP.BRCHNO = LN.BRCHNO
                                    AND SEXP.CURCODE = LN.CURCODE
                            LEFT JOIN GP_INST_SUSP SINC
                                ON SINC.INSTID = LN.INSTID
                                    AND SINC.ACNTCODE = 'LONRESINC'
                                    AND SINC.STATUSID = 1
                                    AND SINC.BRCHNO = LN.BRCHNO
                                    AND SINC.CURCODE = LN.CURCODE
                            LEFT JOIN GP_INST_SUSP SRES
                                ON SRES.INSTID = LN.INSTID
                                    AND SRES.ACNTCODE = 'LONRES'
                                    AND SRES.STATUSID = 1
                                    AND SRES.BRCHNO = LN.BRCHNO
                                    AND SRES.CURCODE = LN.CURCODE
                        WHERE LN.CLSCODE >= 1
                            AND ABS(ROUND(ROUND(LN.PRINCBAL * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0), 2)) > 0.01
                            AND LN.CLOSEDTYPE <> 1
       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_RES_ACCOUNT_BAL_CALC");
    }
};
