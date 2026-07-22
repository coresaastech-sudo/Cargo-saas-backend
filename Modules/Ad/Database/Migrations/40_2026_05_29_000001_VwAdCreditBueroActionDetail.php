<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ad_credit_info_buero_action мөр бүр ямар бодит мэдээлэлд (зээл, хуваарь,
     * төлөлт, барьцаа, хамтран зээлдэгч г.м) холбогдож байгааг харах view.
     *
     * key/parent_key-ийн утга төрлөөс шалтгаалж өөр өөр хүснэгт рүү заадаг:
     *   customer_data        : key = cr_cust_ind/org.id
     *   o_c_loan_information  : key = ln_account.acntno
     *   o_c_loan_schedule     : key = ln_schd.id,           parent_key = acntno
     *   o_c_loan_payment      : key = ln_txn.jrno,          parent_key = acntno
     *   o_c_related_customers : key = cr_cust_ind.id
     *   o_c_related_orgs      : key = cr_cust_org.id
     *   o_shareholder_customer: key = регистр (cr_cust_ind.id1)
     *   o_shareholder_org     : key = регистр (cr_cust_org.id1)
     *   o_c_coll_information   : key = morno | morno+acntno+id
     *   o_c_coll_customer/org  : parent_key = morno
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_ACTION_DETAIL");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_CREDIT_BUERO_ACTION_DETAIL AS
            SELECT
                A.ID,
                A.INSTID,
                A.TYPE,
                A.ACTION,
                A.STATUSID,
                A.KEY,
                A.PARENT_KEY,
                A.REGNO,
                A.CREATED_AT,
                A.UPDATED_AT,

                -- Үндсэн зээлдэгч (regno-оор)
                COALESCE(BI.NAME, BO.NAME) AS BORROWER_NAME,
                A.REGNO AS BORROWER_REGNO,

                -- Холбогдсон бодит бичлэгийн нэр / регистр
                CASE A.TYPE
                    WHEN 'customer_data'          THEN COALESCE(BI.NAME, BO.NAME)
                    WHEN 'o_c_related_customers'  THEN RC.NAME
                    WHEN 'o_c_related_orgs'       THEN RO.NAME
                    WHEN 'o_shareholder_customer' THEN SHC.NAME
                    WHEN 'o_shareholder_org'      THEN SHO.NAME
                    WHEN 'o_c_coll_information'   THEN LMI.NAME
                    WHEN 'o_c_coll_customer'      THEN LMO.NAME
                    WHEN 'o_c_coll_org'           THEN LMO.NAME
                    ELSE NULL
                END AS REF_NAME,

                CASE A.TYPE
                    WHEN 'o_c_related_customers'  THEN RC.ID1
                    WHEN 'o_c_related_orgs'       THEN RO.ID1
                    WHEN 'o_shareholder_customer' THEN A.KEY
                    WHEN 'o_shareholder_org'      THEN A.KEY
                    WHEN 'o_c_coll_information'   THEN LMI.REGNO
                    ELSE NULL
                END AS REF_REGNO,

                -- Хүний унших тайлбар
                CASE A.TYPE
                    WHEN 'customer_data'
                        THEN 'Зээлдэгч: ' || COALESCE(BI.NAME, BO.NAME, A.REGNO)
                    WHEN 'o_c_loan_information'
                        THEN 'Зээл: ' || A.KEY || ' (үлдэгдэл ' || COALESCE(LA.PRINCBAL::TEXT, '-') || ')'
                    WHEN 'o_c_loan_schedule'
                        THEN 'Хуваарь: ' || COALESCE(SC.PAYDAY::TEXT, '-')
                             || ' үндсэн ' || COALESCE(SC.PAYAMOUNT::TEXT, '0')
                             || ' үлдэгдэл ' || COALESCE(SC.THEORBAL::TEXT, '0')
                             || ' (зээл ' || A.PARENT_KEY || ')'
                    WHEN 'o_c_loan_payment'
                        THEN 'Төлөлт jrno ' || A.KEY
                             || ' огноо ' || COALESCE(PAY.POSTDATE::TEXT, '-')
                             || ' (зээл ' || A.PARENT_KEY || ')'
                    WHEN 'o_c_related_customers'
                        THEN 'Хамтран зээлдэгч (иргэн): ' || COALESCE(RC.NAME, '-') || ' ' || COALESCE(RC.ID1, '')
                    WHEN 'o_c_related_orgs'
                        THEN 'Холбогдох байгууллага: ' || COALESCE(RO.NAME, '-') || ' ' || COALESCE(RO.ID1, '')
                    WHEN 'o_shareholder_customer'
                        THEN 'Хувьцаа эзэмшигч (иргэн): ' || COALESCE(SHC.NAME, '-') || ' ' || A.KEY
                    WHEN 'o_shareholder_org'
                        THEN 'Хувьцаа эзэмшигч (ХЭ): ' || COALESCE(SHO.NAME, '-') || ' ' || A.KEY
                    WHEN 'o_c_coll_information'
                        THEN 'Барьцаа морно: ' || COALESCE(LMI.MORNO, A.KEY) || ' ' || COALESCE(LMI.DOCDESC, '')
                    WHEN 'o_c_coll_customer'
                        THEN 'Барьцааны эзэмшигч (иргэн), морно ' || A.PARENT_KEY || ' ' || COALESCE(LMO.NAME, '')
                    WHEN 'o_c_coll_org'
                        THEN 'Барьцааны эзэмшигч (ХЭ), морно ' || A.PARENT_KEY || ' ' || COALESCE(LMO.NAME, '')
                    WHEN 'o_c_customer_bank_relation'
                        THEN 'Банктай холбоотой этгээд: ' || A.KEY
                    ELSE A.TYPE
                END AS REF_INFO

            FROM AD_CREDIT_INFO_BUERO_ACTION A

                -- Үндсэн зээлдэгч
                LEFT JOIN CR_CUST_IND BI
                    ON BI.INSTID = A.INSTID AND BI.ID1 = A.REGNO AND BI.STATUSID <> -1
                LEFT JOIN CR_CUST_ORG BO
                    ON BO.INSTID = A.INSTID AND BO.ID1 = A.REGNO AND BO.STATUSID <> -1

                -- Хамтран зээлдэгч / холбогдох (key = id)
                LEFT JOIN CR_CUST_IND RC
                    ON A.TYPE = 'o_c_related_customers'
                    AND RC.INSTID = A.INSTID AND RC.ID::TEXT = A.KEY AND RC.STATUSID <> -1
                LEFT JOIN CR_CUST_ORG RO
                    ON A.TYPE = 'o_c_related_orgs'
                    AND RO.INSTID = A.INSTID AND RO.ID::TEXT = A.KEY AND RO.STATUSID <> -1

                -- Хувьцаа эзэмшигч (key = регистр id1)
                LEFT JOIN CR_CUST_IND SHC
                    ON A.TYPE = 'o_shareholder_customer'
                    AND SHC.INSTID = A.INSTID AND SHC.ID1 = A.KEY AND SHC.STATUSID <> -1
                LEFT JOIN CR_CUST_ORG SHO
                    ON A.TYPE = 'o_shareholder_org'
                    AND SHO.INSTID = A.INSTID AND SHO.ID1 = A.KEY AND SHO.STATUSID <> -1

                -- Зээл (loan_information key = acntno; schedule/payment parent_key = acntno)
                LEFT JOIN LN_ACCOUNT LA
                    ON LA.INSTID = A.INSTID
                    AND LA.ACNTNO = CASE
                        WHEN A.TYPE = 'o_c_loan_information' THEN A.KEY
                        WHEN A.TYPE IN ('o_c_loan_schedule', 'o_c_loan_payment') THEN A.PARENT_KEY
                    END

                -- Эргэн төлөлтийн хуваарь (key = ln_schd.id)
                LEFT JOIN LN_SCHD SC
                    ON A.TYPE = 'o_c_loan_schedule'
                    AND SC.INSTID = A.INSTID AND SC.ID::TEXT = A.KEY

                -- Төлөлт (key = ln_txn.jrno)
                LEFT JOIN (
                    SELECT JRNO, ACNTNO, INSTID, MIN(POSTDATE) AS POSTDATE
                    FROM LN_TXN
                    GROUP BY JRNO, ACNTNO, INSTID
                ) PAY
                    ON A.TYPE = 'o_c_loan_payment'
                    AND PAY.INSTID = A.INSTID AND PAY.JRNO::TEXT = A.KEY

                -- Барьцаа хөрөнгө (coll_information key = morno эсвэл morno+acntno+id)
                LEFT JOIN LN_MOR LMI
                    ON A.TYPE = 'o_c_coll_information'
                    AND LMI.INSTID = A.INSTID
                    AND LMI.STATUSID = 1
                    AND (A.KEY = LMI.MORNO OR A.KEY LIKE LMI.MORNO || '%')

                -- Барьцааны эзэмшигч (parent_key = morno)
                LEFT JOIN LN_MOR LMO
                    ON A.TYPE IN ('o_c_coll_customer', 'o_c_coll_org')
                    AND LMO.INSTID = A.INSTID
                    AND LMO.STATUSID = 1
                    AND LMO.MORNO = A.PARENT_KEY
        ");
    }

    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_ACTION_DETAIL");
    }
};
