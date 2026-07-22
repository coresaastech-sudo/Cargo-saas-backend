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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUSTIND_LISTS");
        DB::unprepared("CREATE OR REPLACE VIEW VW_CR_CUSTIND_LISTS AS
                        SELECT IND.ID AS ID,
                            IND.CUSTNO AS CUSTNO,
                            IND.BRCHNO AS BRCHNO,
                            IND.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                            IND.LNAME AS LNAME,
                            IND.LNAME2 AS LNAME2,
                            IND.SEGCODE AS SEGCODE,
                            IND.BIRTHDATE AS BIRTHDATE,
                            IND.NAME AS NAME,
                            IND.NAME2 AS NAME2,
                            IND.ID1 AS ID1,
                            IND.BL AS BL,
                            IND.CUSTTYPECODE AS CUSTTYPECODE,
                            IND.INSTID AS INSTID,
                            IND.STATUSID AS STATUSID,
                            IND.HANDPHONE AS PHONE,
                            IND.LOANCOUNT,
                            IND.HIDDEN,
                            GP1.NAME AS SEGCODE_NAME,
                            GP2.NAME AS INDUCODE_NAME,
                            GP3.NAME AS INDUSUBCODE_NAME,
                            GP4.NAME AS CATCODE_NAME,
                            IND.TXNDATE,
                            IND.PARTNER_TYPE
                        FROM CR_CUST_IND IND
                        LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = IND.INSTID AND IND.BRCHNO = BR.BRCHNO
                        LEFT JOIN VW_DICT_GP_CONST_006 GP1 ON GP1.VALUE = CAST(IND.SEGCODE AS VARCHAR) AND GP1.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_064 GP2 ON GP2.VALUE = CAST(IND.INDUCODE AS VARCHAR) AND GP2.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_002 GP3 ON GP3.VALUE = CAST(IND.INDUSUBCODE AS VARCHAR) AND GP3.PARENT_CODE = 'indusubcode' AND GP3.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_008 GP4 ON GP4.VALUE = CAST(IND.CATCODE AS VARCHAR) AND GP4.INSTID IN (1, IND.INSTID)
                        WHERE IND.STATUSID >= 0
                       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUSTIND_LISTS");
    }
};
