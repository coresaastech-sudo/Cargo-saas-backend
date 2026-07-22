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
        DB::statement("DROP VIEW IF EXISTS VW_AP_ACNT_LIST");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AP_ACNT_LIST AS
                SELECT
                    CC.ACNTNO,
                    CC.CUSTNO,
                    CC.CURCODE,
                    CC.NAME2,
                    CC.PRODCODE,
                    CC.STATUSID,
                    CC.INSTID,
                    CC.CLSCODE,
                    CC.LOANTYPE,
                    CC.ADVDATE,
                    CC.PRINCBAL,
                    CC.CAPBINT,
                    CC.CAPCINT,
                    CC.CAPFINT,
                    CC.BASEINT2CAP,
                    CC.COMINT2CAP,
                    CC.FINEINT2CAP,
                    CC.ADJBINT2CAP,
                    CC.ADJCINT2CAP,
                    CC.ADJFINT2CAP,
                    CC.CTACNTNO,
                    CC.CTCOMACNTNO,
                    CC.CTFINEACNTNO,
                    CC.SEGCODE,
                    CC.HIDE,
                    CC.OPENEDDATE,
                    CC.CREATED_AT,
                    CC.UPDATED_AT,
                    CC.NAME AS NAME1,
                    CC.APPROVAMOUNT,
                    CC.ACNTNO || ' - ' || CC.NAME AS NAME,
                    CC.ARREARDATE,
                    CC.ARREARDATEINT,
                    TO_DATE(SEQ.SEQNO, 'YYYY-MM-DD') - CC.ARREARDATE AS NUMARREARDATEDIFF,
                    TO_DATE(SEQ.SEQNO, 'YYYY-MM-DD') - CC.ARREARDATEINT AS NUMARREARDATEINTDIFF,
                    CC.RISKMANAGER,
                    CC.ANALYSISMANAGER,
                    CC.AUDITMANAGER,
                    CC.CREATED_BY,
                    CC.TRACKNO,
                    CC.DUEPRINC,
                    TRACKDIC.NAME AS TRACK_NAME,
                    CLSDIC.NAME AS CLSCODE_NAME,
                    PROD.NAME AS PROD_NAME,
                    PROD.ISPAYBASEINT,
                    PROD.ISPAYFINEINT,
                    PROD.ISPAYCOMINT,
                    PROD.ISPAYCTINT,
                    CU.ID1,
                    CU.PHONE,
                    CU.HIDDEN,
                    CC.RISKMANAGER || ' - ' || GU1.NAME AS RISKMANAGER_NAME,
                    CC.ANALYSISMANAGER || ' - ' || GU2.NAME AS ANALYSISMANAGER_NAME,
                    CC.AUDITMANAGER || ' - ' || GU3.NAME AS AUDITMANAGER_NAME,
                    CC.CREATED_BY || ' - ' || GU4.NAME AS CREATED_BY_NAME,
                    CASE WHEN CU.LNAME = '' THEN CU.NAME
                    ELSE CU.LNAME || ' ' || CU.NAME
                    END AS CUST_NAME,
                    PROD.PRODCODE || ' - ' || PROD.NAME AS PRODCODE_NAME,
                    BR.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                    BR.BRCHNO,
                    (CC.BASEINTDAILY * (CC.NEXTPAYDAY - TO_DATE(SEQ.SEQNO, 'YYYY-MM-DD')) +  CC.BASEINT2CAP + CC.ADJBINT2CAP) +
                    (CC.COMINTDAILY * (CC.NEXTPAYDAY - TO_DATE(SEQ.SEQNO, 'YYYY-MM-DD')) +  CC.COMINT2CAP + CC.ADJCINT2CAP) +
                    (CC.FINEINTDAILY * (CC.NEXTPAYDAY - TO_DATE(SEQ.SEQNO, 'YYYY-MM-DD')) +  CC.FINEINT2CAP + CC.ADJFINT2CAP)
                    AS NEXTPAYSUMINT,
                    CU.CUSTTYPECODE,
                    CU.ID AS CUSTID
                FROM LN_ACCOUNT CC
                    LEFT JOIN VW_CR_CUST_LISTS CU ON CU.CUSTNO = CC.CUSTNO AND CU.INSTID = CC.INSTID
                    LEFT JOIN LN_ACCOUNT_TYPE PROD ON PROD.PRODCODE = CC.PRODCODE AND PROD.INSTID = CC.INSTID
                    LEFT JOIN GP_INST_BRANCH BR ON BR.BRCHNO = CC.BRCHNO AND BR.INSTID = CC.INSTID
                    LEFT JOIN GP_INST_SEQ SEQ ON  SEQ.INSTID = CC.INSTID AND SEQ.SEQID='SYSDATE'
                    LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.RISKMANAGER AND GU1.INSTID = CC.INSTID
                    LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.ANALYSISMANAGER AND GU2.INSTID = CC.INSTID
                    LEFT JOIN GP_INST_USER GU3 ON GU3.ID = CC.AUDITMANAGER AND GU3.INSTID = CC.INSTID
                    LEFT JOIN GP_INST_USER GU4 ON GU4.ID = CC.CREATED_BY AND GU4.INSTID = CC.INSTID
                    LEFT JOIN VW_DICT_GP_CONST_036 CLSDIC ON CLSDIC.VALUE = CAST(CC.CLSCODE AS VARCHAR) AND CLSDIC.INSTID = 1
                    LEFT JOIN VW_DICT_GP_CONST_047 TRACKDIC ON TRACKDIC.VALUE::INTEGER = CC.TRACKNO AND TRACKDIC.INSTID = 1"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_ACNT_LIST");
    }
};
