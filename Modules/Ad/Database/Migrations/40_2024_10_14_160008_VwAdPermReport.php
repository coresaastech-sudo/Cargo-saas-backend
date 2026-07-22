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
        DB::statement("DROP VIEW IF EXISTS VW_AD_PERM_REPORT");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_PERM_REPORT AS
         SELECT
            CC.*,
            RE.ACTION_CODE || ' - ' || RE.NAME AS PC_NAME,
            RE.NAME AS PROCESS_NAME,
            CASE WHEN CC.VALUETYPE = 'U' THEN 'Хэрэглэгч'
                WHEN CC.VALUETYPE = 'R' THEN 'Эрхийн бүлэг'
                WHEN CC.VALUETYPE = 'B' THEN 'Салбар'
                WHEN CC.VALUETYPE = 'S' THEN 'Борлуулалтын ажилтан'
                WHEN CC.VALUETYPE = 'C' THEN 'Хяналтын ажилтан'
                WHEN CC.VALUETYPE = 'A' THEN 'Судалгааны ажилтан'
                WHEN CC.VALUETYPE = 'M' THEN 'Эрсдэлийн шинжээч'
                ELSE ''
            END AS VALUETYPE_NAME,
            CASE WHEN (
                CC.VALUETYPE = 'U' OR CC.VALUETYPE = 'S' OR CC.VALUETYPE = 'C'
                OR CC.VALUETYPE = 'A' OR CC.VALUETYPE = 'M'
            ) THEN GU0.ID || ' - ' || GU0.NAME
                WHEN CC.VALUETYPE = 'R' THEN IR.ID || ' - ' || IR.ROLENAME
                WHEN CC.VALUETYPE = 'B' THEN BR.BRCHNO || ' - ' || BR.NAME
                ELSE ''
            END AS MAIN_NAME,
            GU0.NAME AS USER_NAME,
            GU0.ID || ' - ' || GU0.NAME AS USERID_NAME,
            BR.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
            CASE WHEN CC.SHOWBRCHNO = 'ALL' THEN 'ALL - БҮГД'
                ELSE BR1.BRCHNO || ' - ' || BR1.NAME END AS SHOWBRCHNO_NAME,
            GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
            GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
        FROM
            AD_PERM_REPORT CC
            LEFT JOIN GP_INST_USER GU0 ON GU0.ID = CC.USERID
            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
            LEFT JOIN GP_INST_BRANCH BR ON BR.BRCHNO = CC.BRCHNO AND BR.INSTID = CC.INSTID AND BR.STATUSID = 1
            LEFT JOIN GP_INST_BRANCH BR1 ON BR1.BRCHNO = CC.SHOWBRCHNO AND BR1.INSTID = CC.INSTID AND BR1.STATUSID = 1
            LEFT JOIN GP_INST_ROLE IR ON IR.ID = CC.ROLEID AND IR.INSTID = CC.INSTID AND IR.STATUSID = 1
            LEFT JOIN GP_INST_PERMS PR ON PR.AC = CC.AC AND PR.INSTID = CC.INSTID AND PR.STATUSID = 1
            JOIN RE_INST_REPORT_TEMP RE ON RE.ACTION_CODE = PR.AC AND RE.STATUSID = 1
            "
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_PERM_REPORT");
    }
};
