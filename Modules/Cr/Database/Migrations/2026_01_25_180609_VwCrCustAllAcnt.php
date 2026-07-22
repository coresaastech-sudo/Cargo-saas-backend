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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_All_ACNT");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_All_ACNT AS
                       SELECT
                            ACNT.*,
                            CR.ID AS CUSTID,
                            CR.ID1 AS ID1,
                            CR.NAME AS CUSTNAME,
                            CR.NAME2 AS CUSTNAME2,
                            CR.CUSTTYPECODE AS CUSTTYPECODE,
                            CASE
                                WHEN CLS.NAME is null THEN 'Ангилалгүй'
                                ELSE CLS.NAME
                            END AS CLSCODE_NAME
                            FROM
                            (
                            SELECT
                            DP.ACNTNO AS ACNTNO,
                            DP.CURRENTBAL AS CURRENTBAL,
                            0 AS ADVAMOUNT,
                            'Үндсэн' AS ACNTROLE,
                            'DP' AS ACNTMODE,
                            DP.STATUSID AS STATUSID,
                            DP.BRCHNO AS BRCHNO,
                            DP.PRODCODE AS PRODCODE,
                            DP.CUSTNO AS CUSTNO,
                            DP.NAME AS NAME,
                            DP.NAME2 AS NAME2,
                            DP.CURCODE AS CURCODE,
                            DP.ODCLSCODE AS CLSCODE,
                            DP.HIDE AS HIDE,
                            DP.INSTID AS INSTID,
                            DP.CREATED_BY AS CREATED_BY,
                            DP.CREATED_AT AS CREATED_AT,
                            DP.UPDATED_BY AS UPDATED_BY,
                            DP.UPDATED_AT AS UPDATED_AT
                            FROM
                            DP_ACCOUNT DP
                            UNION ALL
                            SELECT
                            DP.ACNTNO AS ACNTNO,
                            DP.CURRENTBAL AS CURRENTBAL,
                            0 AS ADVAMOUNT,
                            'Хамтран эзэмшигч' AS ACNTROLE,
                            'DP' AS ACNTMODE,
                            DP.STATUSID AS STATUSID,
                            DP.BRCHNO AS BRCHNO,
                            DP.PRODCODE AS PRODCODE,
                            CO.CUSTNO AS CUSTNO,
                            DP.NAME AS NAME,
                            DP.NAME2 AS NAME2,
                            DP.CURCODE AS CURCODE,
                            DP.ODCLSCODE AS CLSCODE,
                            DP.HIDE AS HIDE,
                            DP.INSTID AS INSTID,
                            DP.CREATED_BY AS CREATED_BY,
                            DP.CREATED_AT AS CREATED_AT,
                            DP.UPDATED_BY AS UPDATED_BY,
                            DP.UPDATED_AT AS UPDATED_AT
                            FROM DP_ACCOUNT_CUST CO
                            LEFT JOIN DP_ACCOUNT DP ON CO.INSTID = DP.INSTID AND CO.ACNTNO = DP.ACNTNO
                            WHERE CO.STATUSID = 1
                            UNION ALL
                            SELECT
                            LN.ACNTNO AS ACNTNO,
                            LN.PRINCBAL AS CURRENTBAL,
                            LN.ADVAMOUNT AS ADVAMOUNT,
                            'Үндсэн' AS ACNTROLE,
                            'LN' AS ACNTMODE,
                            LN.STATUSID AS STATUSID,
                            LN.BRCHNO AS BRCHNO,
                            LN.PRODCODE AS PRODCODE,
                            LN.CUSTNO AS CUSTNO,
                            LN.NAME AS NAME,
                            LN.NAME2 AS NAME2,
                            LN.CURCODE AS CURCODE,
                            LN.CLSCODE AS CLSCODE,
                            LN.HIDE AS HIDE,
                            LN.INSTID AS INSTID,
                            LN.CREATED_BY AS CREATED_BY,
                            LN.CREATED_AT AS CREATED_AT,
                            LN.UPDATED_BY AS UPDATED_BY,
                            LN.UPDATED_AT AS UPDATED_AT
                            FROM
                            LN_ACCOUNT LN
                            UNION ALL
                            SELECT
                            LN.ACNTNO AS ACNTNO,
                            LN.PRINCBAL AS CURRENTBAL,
                            LN.ADVAMOUNT AS ADVAMOUNT,
                            DIC.NAME AS ACNTROLE,
                            'LN' AS ACNTMODE,
                            LN.STATUSID AS STATUSID,
                            LN.BRCHNO AS BRCHNO,
                            LN.PRODCODE AS PRODCODE,
                            CO.CUSTNO AS CUSTNO,
                            LN.NAME AS NAME,
                            LN.NAME2 AS NAME2,
                            LN.CURCODE AS CURCODE,
                            LN.CLSCODE AS CLSCODE,
                            LN.HIDE AS HIDE,
                            LN.INSTID AS INSTID,
                            LN.CREATED_BY AS CREATED_BY,
                            LN.CREATED_AT AS CREATED_AT,
                            LN.UPDATED_BY AS UPDATED_BY,
                            LN.UPDATED_AT AS UPDATED_AT
                            FROM LN_ACCOUNT_CUST CO
                            LEFT JOIN LN_ACCOUNT LN ON CO.INSTID = LN.INSTID AND CO.ACNTNO = LN.ACNTNO
                            LEFT JOIN GP_CONST DIC ON DIC.VALUE = CAST(CO.ROLECODE AS VARCHAR) AND DIC.PARENT_CODE='coborrower_role'
                            WHERE CO.STATUSID = 1
                            UNION ALL
                            SELECT
                            CT.ACNTNO AS ACNTNO,
                            CT.CURRENTBAL AS CURRENTBAL,
                            0 AS ADVAMOUNT,
                            'Үндсэн' AS ACNTROLE,
                            'CT' AS ACNTMODE,
                            CT.STATUSID AS STATUSID,
                            CT.BRCHNO AS BRCHNO,
                            CT.TYPECODE AS PRODCODE,
                            CT.CUSTNO AS CUSTNO,
                            CT.NAME AS NAME,
                            CT.NAME2 AS NAME2,
                            CT.CURCODE AS CURCODE,
                            0 AS CLSCODE,
                            CT.HIDE AS HIDE,
                            CT.INSTID AS INSTID,
                            CT.CREATED_BY AS CREATED_BY,
                            CT.CREATED_AT AS CREATED_AT,
                            CT.UPDATED_BY AS UPDATED_BY,
                            CT.UPDATED_AT AS UPDATED_AT
                            FROM
                            IA_CT_ACCOUNT CT
                            UNION ALL
                            SELECT
                            DE.ACNTNO AS ACNTNO,
                            DE.CURRENTBAL AS CURRENTBAL,
                            0 AS ADVAMOUNT,
                            'Үндсэн' AS ACNTROLE,
                            'DE' AS ACNTMODE,
                            DE.STATUSID AS STATUSID,
                            DE.BRCHNO AS BRCHNO,
                            DE.PRODCODE AS PRODCODE,
                            DE.LINKED_CUSTNO AS CUSTNO,
                            DEP.NAME AS NAME,
                            DEP.NAME2 AS NAME2,
                            DE.CURCODE AS CURCODE,
                            0 AS CLSCODE,
                            '0' AS HIDE,
                            DE.INSTID AS INSTID,
                            DE.CREATED_BY AS CREATED_BY,
                            DE.CREATED_AT AS CREATED_AT,
                            DE.UPDATED_BY AS UPDATED_BY,
                            DE.UPDATED_AT AS UPDATED_AT
                            FROM
                            IA_DE_ACCOUNT DE
                            LEFT JOIN IA_DE_ACCOUNT_TYPE DEP ON DE.INSTID = DEP.INSTID AND DE.PRODCODE = DEP.PRODCODE
                            WHERE DE.STATUSID = 1
                            ) ACNT
                            LEFT JOIN VW_CR_CUST_LISTS CR
                            ON CR.CUSTNO = ACNT.CUSTNO
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
        DB::statement("DROP VIEW VW_CR_CUST_All_ACNT");
    }
};
