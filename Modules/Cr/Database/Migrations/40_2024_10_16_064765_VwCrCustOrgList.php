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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUSTORG_LISTS");
        DB::unprepared("CREATE OR REPLACE VIEW VW_CR_CUSTORG_LISTS AS
                        SELECT ORG.ID AS ID,
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
                            GP1.NAME AS SEGCODE_NAME,
                            GP2.NAME AS ORGTYPECODE_NAME,
                            GP3.NAME AS INDUCODE_NAME,
                            GP4.NAME AS INDUSUBCODE_NAME,
                            GP5.NAME AS CATCODE_NAME,
                            ORG.TXNDATE,
                            ORG.PARTNER_TYPE
                        FROM CR_CUST_ORG ORG
                        LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = ORG.INSTID AND ORG.BRCHNO = BR.BRCHNO
                        LEFT JOIN VW_DICT_GP_CONST_006 GP1 ON GP1.VALUE= CAST(ORG.SEGCODE AS VARCHAR) AND GP1.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_020 GP2 ON GP2.VALUE= CAST(ORG.ORGTYPECODE AS VARCHAR) AND GP2.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_064 GP3 ON GP3.VALUE= CAST(ORG.INDUCODE AS VARCHAR) AND GP3.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_002 GP4 ON GP4.VALUE= CAST(ORG.INDUSUBCODE AS VARCHAR) AND GP4.PARENT_CODE = 'indusubcode' AND GP4.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_008 GP5 ON GP5.VALUE= CAST(ORG.CATCODE AS VARCHAR) AND GP5.INSTID IN (1, ORG.INSTID)
                        WHERE ORG.STATUSID >= 0");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUSTORG_LISTS");
    }
};
