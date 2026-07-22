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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_All_ACNT_WITH_BALANCE");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_All_ACNT_WITH_BALANCE AS
                        SELECT ACNT.*,
                            CR.ID AS CUSTID,
                            CR.NAME AS CUSTNAME,
                            CR.NAME2 AS CUSTNAME2,
                            CR.CUSTTYPECODE AS CUSTTYPECODE,
                            CASE
                                            WHEN CLS.NAME IS NULL THEN 'Ангилалгүй'
                                            ELSE CLS.NAME
                            END AS CLSCODE_NAME
                        FROM
                            (
                                    (SELECT DP.ACNTNO AS ACNTNO,
                                            CASE
                                                            WHEN AT.PROCFLAG = 'T' THEN 'TD'
                                                            ELSE 'DP'
                                            END AS ACNTMODE,
                                            DP.STATUSID AS STATUSID,
                                            DP.BRCHNO AS BRCHNO,
                                            DP.PRODCODE AS PRODCODE,
                                            AT.NAME AS PROD_NAME,
                                            AT.NAME2 AS PROD_NAME2,
                                            DP.CUSTNO AS CUSTNO,
                                            DP.NAME AS NAME,
                                            DP.NAME2 AS NAME2,
                                            DP.CURCODE AS CURCODE,
                                            DP.ODCLSCODE AS CLSCODE,
                                            DP.CURRENTBAL AS BALANCE,
                                            DP.INSTID AS INSTID,
                                            DP.CREATED_BY AS CREATED_BY,
                                            DP.CREATED_AT AS CREATED_AT,
                                            DP.UPDATED_BY AS UPDATED_BY,
                                            DP.UPDATED_AT AS UPDATED_AT
                                        FROM DP_ACCOUNT DP
                                        LEFT JOIN DP_ACCOUNT_TYPE AT ON AT.PRODCODE = DP.PRODCODE
                                            AND AT.INSTID = DP.INSTID)
                                UNION ALL (SELECT LN.ACNTNO AS ACNTNO,
                                    CASE WHEN LT.REDRAW = 1 THEN 'LINE' ELSE 'LN' END AS ACNTMODE,
                                    LN.STATUSID AS STATUSID,
                                    LN.BRCHNO AS BRCHNO,
                                    LN.PRODCODE AS PRODCODE,
                                    LT.NAME AS PROD_NAME,
                                    LT.NAME2 AS PROD_NAME2,
                                    LN.CUSTNO AS CUSTNO,
                                    LN.NAME AS NAME,
                                    LN.NAME2 AS NAME2,
                                    LN.CURCODE AS CURCODE,
                                    LN.CLSCODE AS CLSCODE,
                                    LN.PRINCBAL AS BALANCE,
                                    LN.INSTID AS INSTID,
                                    LN.CREATED_BY AS CREATED_BY,
                                    LN.CREATED_AT AS CREATED_AT,
                                    LN.UPDATED_BY AS UPDATED_BY,
                                    LN.UPDATED_AT AS UPDATED_AT
                                FROM LN_ACCOUNT LN
                                LEFT JOIN LN_ACCOUNT_TYPE LT ON LN.PRODCODE = LT.PRODCODE
                                    AND LT.INSTID = LN.INSTID)
                                UNION ALL SELECT CT.ACNTNO AS ACNTNO,
                                    'CT' AS ACNTMODE,
                                    CT.STATUSID AS STATUSID,
                                    CT.BRCHNO AS BRCHNO,
                                    CT.TYPECODE AS PRODCODE,
                                    IT.NAME AS PROD_NAME,
                                    IT.NAME2 AS PROD_NAME2,
                                    CT.CUSTNO AS CUSTNO,
                                    CT.NAME AS NAME,
                                    CT.NAME2 AS NAME2,
                                    CT.CURCODE AS CURCODE,
                                    0 AS CLSCODE,
                                    CT.CURRENTBAL AS BALANCE,
                                    CT.INSTID AS INSTID,
                                    CT.CREATED_BY AS CREATED_BY,
                                    CT.CREATED_AT AS CREATED_AT,
                                    CT.UPDATED_BY AS UPDATED_BY,
                                    CT.UPDATED_AT AS UPDATED_AT
                                FROM IA_CT_ACCOUNT CT
                                LEFT JOIN IA_CT_ACCOUNT_TYPE IT ON IT.TYPECODE = CT.TYPECODE
                                    AND IT.INSTID = CT.INSTID) ACNT
                        LEFT JOIN VW_CR_CUST_LISTS CR ON CR.CUSTNO = ACNT.CUSTNO
                            AND CR.INSTID = ACNT.INSTID
                        LEFT JOIN VW_DICT_GP_CONST_036 CLS ON CLS.VALUE::INTEGER = ACNT.CLSCODE
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_All_ACNT_WITH_BALANCE");
    }
};
