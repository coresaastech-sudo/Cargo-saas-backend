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
        DB::statement("DROP VIEW IF EXISTS VW_AD_IA_RES_ACCOUNT_BAL_CALC");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_IA_RES_ACCOUNT_BAL_CALC AS
                        SELECT CC.INSTID,
                            CC.ACNTNO,
                            ABS(CC.CURRENTBAL) AS CURRENTBAL,
                            CC.CURCODE,
                            CC.CLSCODE,
                            CC.CLSCODE || ' - ' || DM.NAME AS CLSCODE_NAME,
                            DM.VALUE_ADD2::INT AS PER,
                            ROUND((ABS(CC.CURRENTBAL) * DM.VALUE_ADD2::INT / 100) ,2) AS NEWRESBAL,
                            COALESCE (RS.ACNTTYPE, 'IA') AS RESACNTTYPE,
                            RS.RESDATE,
                            COALESCE (RS.BALANCE, 0) AS BALANCE,
                            COALESCE (RS.CLSCODE, 1) AS RESCLS,
                            COALESCE (RS.CLSCODE, 1) || ' - ' || DM1.NAME AS RESCLS_NAME,
                            COALESCE (RS.RESBAL, 0) AS RESBAL,
                            ROUND((ROUND(ABS(CC.CURRENTBAL) * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) AS AMOUNT,
                            CASE
                                WHEN ROUND((ROUND(ABS(CC.CURRENTBAL) * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTTYPE
                                ELSE SINC.ACNTTYPE
                            END AS CONT_ACNTTYPE,
                            CASE
                                WHEN ROUND((ROUND(ABS(CC.CURRENTBAL) * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0)), 2) > 0
                                THEN SEXP.ACNTNO
                                ELSE SINC.ACNTNO
                            END AS CONT_ACNTNO,
                            SRES.ACNTTYPE AS RES_ACNTTYPE,
                            SRES.ACNTNO AS RES_ACNTNO,
                            CC.AUTORISKFUND
                        FROM VW_IA_ACNT_LIST CC
                            LEFT JOIN GP_CONST DM
                                ON DM.PARENT_CODE = 'clscode' AND DM.VALUE::INT = CC.CLSCODE
                            LEFT JOIN (
                                select * from AD_RES_ACCOUNT_BAL where id in (
                                    select max(id) from AD_RES_ACCOUNT_BAL where STATUSID = 1 and ACNTTYPE = 'IA' group by ACNTNO, STATUSID, INSTID
                                )
                            )  RS
                                ON RS.INSTID = CC.INSTID AND RS.ACNTNO = CC.ACNTNO::TEXT
                            LEFT JOIN GP_CONST DM1
                                ON DM1.PARENT_CODE = 'clscode' AND DM1.VALUE::INT = COALESCE (RS.CLSCODE, 1)
                            LEFT JOIN GP_INST_SUSP SEXP
                                ON SEXP.INSTID = CC.INSTID
                                    AND SEXP.ACNTCODE = 'OFRESEXP'
                                    AND SEXP.STATUSID = 1
                                    AND SEXP.BRCHNO = CC.BRCHNO
                                    AND SEXP.CURCODE = CC.CURCODE
                            LEFT JOIN GP_INST_SUSP SINC
                                ON SINC.INSTID = CC.INSTID
                                    AND SINC.ACNTCODE = 'OFRESINC'
                                    AND SINC.STATUSID = 1
                                    AND SINC.BRCHNO = CC.BRCHNO
                                    AND SINC.CURCODE = CC.CURCODE
                            LEFT JOIN GP_INST_SUSP SRES
                                ON SRES.INSTID = CC.INSTID
                                    AND SRES.ACNTCODE = 'OFRES'
                                    AND SRES.STATUSID = 1
                                    AND SRES.BRCHNO = CC.BRCHNO
                                    AND SRES.CURCODE = CC.CURCODE
                        WHERE CC.CLSCODE >= 1
                            AND CC.ACNTCHAR = 'A'
                            AND ABS(ROUND(ROUND(ABS(CC.CURRENTBAL) * DM.VALUE_ADD2::INT / 100, 2) - COALESCE (RS.RESBAL, 0), 2)) > 0.01
       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_IA_RES_ACCOUNT_BAL_CALC");
    }
};
