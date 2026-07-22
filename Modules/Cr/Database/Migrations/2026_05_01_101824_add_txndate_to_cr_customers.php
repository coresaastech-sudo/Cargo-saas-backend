<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cr_cust_ind', function (Blueprint $table) {
            if (!Schema::hasColumn('cr_cust_ind', 'txndate')) {
                $table->date('txndate')->nullable()->after('instid')->comment('Customer registration transaction date');
            }
        });

        Schema::table('cr_cust_org', function (Blueprint $table) {
            if (!Schema::hasColumn('cr_cust_org', 'txndate')) {
                $table->date('txndate')->nullable()->after('instid')->comment('Customer registration transaction date');
            }
        });

        DB::statement("UPDATE cr_cust_ind SET txndate = created_at::date WHERE txndate IS NULL AND created_at IS NOT NULL");
        DB::statement("UPDATE cr_cust_org SET txndate = created_at::date WHERE txndate IS NULL AND created_at IS NOT NULL");

        $this->createViews();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->createViews(false);

        Schema::table('cr_cust_ind', function (Blueprint $table) {
            if (Schema::hasColumn('cr_cust_ind', 'txndate')) {
                $table->dropColumn('txndate');
            }
        });

        Schema::table('cr_cust_org', function (Blueprint $table) {
            if (Schema::hasColumn('cr_cust_org', 'txndate')) {
                $table->dropColumn('txndate');
            }
        });
    }

    private function createViews($withTxnDate = true)
    {
        $indTxnDate = $withTxnDate ? ",\n                            IND.TXNDATE" : '';
        $orgTxnDate = $withTxnDate ? ",\n                            ORG.TXNDATE" : '';
        $listIndTxnDate = $withTxnDate ? ",\n                                IND.TXNDATE" : '';
        $listOrgTxnDate = $withTxnDate ? ",\n                                ORG.TXNDATE" : '';

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
                            GP4.NAME AS CATCODE_NAME{$indTxnDate}
                        FROM CR_CUST_IND IND
                        LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = IND.INSTID AND IND.BRCHNO = BR.BRCHNO
                        LEFT JOIN VW_DICT_GP_CONST_006 GP1 ON GP1.VALUE = CAST(IND.SEGCODE AS VARCHAR) AND GP1.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_064 GP2 ON GP2.VALUE = CAST(IND.INDUCODE AS VARCHAR) AND GP2.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_002 GP3 ON GP3.VALUE = CAST(IND.INDUSUBCODE AS VARCHAR) AND GP3.PARENT_CODE = 'indusubcode' AND GP3.INSTID IN (1, IND.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_008 GP4 ON GP4.VALUE = CAST(IND.CATCODE AS VARCHAR) AND GP4.INSTID IN (1, IND.INSTID)
                        WHERE IND.STATUSID >= 0");

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
                            GP5.NAME AS CATCODE_NAME{$orgTxnDate}
                        FROM CR_CUST_ORG ORG
                        LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = ORG.INSTID AND ORG.BRCHNO = BR.BRCHNO
                        LEFT JOIN VW_DICT_GP_CONST_006 GP1 ON GP1.VALUE= CAST(ORG.SEGCODE AS VARCHAR) AND GP1.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_020 GP2 ON GP2.VALUE= CAST(ORG.ORGTYPECODE AS VARCHAR) AND GP2.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_064 GP3 ON GP3.VALUE= CAST(ORG.INDUCODE AS VARCHAR) AND GP3.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_002 GP4 ON GP4.VALUE= CAST(ORG.INDUSUBCODE AS VARCHAR) AND GP4.PARENT_CODE = 'indusubcode' AND GP4.INSTID IN (1, ORG.INSTID)
                        LEFT JOIN VW_DICT_GP_CONST_008 GP5 ON GP5.VALUE= CAST(ORG.CATCODE AS VARCHAR) AND GP5.INSTID IN (1, ORG.INSTID)
                        WHERE ORG.STATUSID >= 0");

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
                                IND.ISPOLITICAL{$listIndTxnDate}
                            FROM CR_CUST_IND IND
                            LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = IND.INSTID AND IND.BRCHNO = BR.BRCHNO
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
                                ORG.ISPOLITICAL{$listOrgTxnDate}
                            FROM CR_CUST_ORG ORG
                            LEFT JOIN GP_INST_BRANCH BR ON BR.INSTID = ORG.INSTID AND ORG.BRCHNO = BR.BRCHNO");
    }
};
