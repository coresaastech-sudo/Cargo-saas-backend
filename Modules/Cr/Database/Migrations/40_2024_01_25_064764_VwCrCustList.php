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

        // DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_LISTS");

        DB::unprepared("CREATE OR REPLACE VIEW VW_CR_CUST_LISTS AS
                            SELECT
                                IND.ID AS ID,
                                IND.CUSTNO AS CUSTNO,
                                IND.LNAME AS LNAME,
                                IND.LNAME2 AS LNAME2,
                                IND.SEGCODE AS SEGCODE,
                                IND.BIRTHDATE AS BIRTHDATE,
                                IND.NAME AS NAME,
                                IND.NAME2 AS NAME2,
                                IND.ID1 AS ID1,
                                IND.BL AS BL,
                                IND.CUSTTYPECODE AS CUSTTYPECODE,
                                IND.BRCHNO AS BRCHNO,
                                IND.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                                IND.INSTID AS INSTID,
                                IND.STATUSID AS STATUSID,
                                IND.HANDPHONE AS PHONE,
                                IND.LOANCOUNT,
                                IND.HIDDEN,
                                IND.ISPOLITICAL,
                                IND.TXNDATE,
                                IND.PARTNER_TYPE
                            FROM CR_CUST_IND IND
                            LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = IND.INSTID AND IND.BRCHNO = BR.BRCHNO
                            -- WHERE IND.STATUSID = 1 20250312 хаагдсан дансны идвэхгүй болгосон харилцагч лавлагаанд гарахгүй байгааг засав
                            UNION ALL
                            SELECT
                                ORG.ID AS ID,
                                ORG.CUSTNO AS CUSTNO,
                                '' AS LNAME,
                                '' AS LNAME2,
                                ORG.SEGCODE AS SEGCODE,
                                ORG.BIRTHDATE AS BIRTHDATE,
                                ORG.NAME AS NAME,
                                ORG.NAME2 AS NAME2,
                                ORG.ID1 AS ID1,
                                ORG.BL AS BL,
                                ORG.CUSTTYPECODE AS CUSTTYPECODE,
                                ORG.BRCHNO AS BRCHNO,
                                ORG.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                                ORG.INSTID AS INSTID,
                                ORG.STATUSID AS STATUSID,
                                ORG.WORKPHONE AS PHONE,
                                ORG.LOANCOUNT,
                                ORG.HIDDEN,
                                ORG.ISPOLITICAL,
                                ORG.TXNDATE,
                                ORG.PARTNER_TYPE
                            FROM CR_CUST_ORG ORG
                            LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = ORG.INSTID AND ORG.BRCHNO = BR.BRCHNO
                            -- WHERE ORG.STATUSID = 1
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_LISTS");
    }
};
