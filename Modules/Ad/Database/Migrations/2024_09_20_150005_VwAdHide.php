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
        DB::statement("DROP VIEW IF EXISTS VW_AD_HIDE");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_HIDE AS
         SELECT
            CC.*,
            CASE WHEN CC.MODULE = 'DP' THEN 'Депозит'
                 WHEN CC.MODULE = 'LN' THEN 'Зээл'
                 WHEN CC.MODULE = 'CT' THEN 'Тэнцлийн гадуурх'
                 WHEN CC.MODULE = 'CR' THEN 'Харилцагч'
                ELSE 'Дотоодын данс'
            END AS MODULE_NAME,
            CASE WHEN CC.VALUETYPE = 'U' THEN 'Хэрэглэгч'
                 WHEN CC.VALUETYPE = 'B' THEN 'Салбар'
                 WHEN CC.VALUETYPE = 'R' THEN 'Эрхийн бүлэг'
                 WHEN CC.VALUETYPE = 'BU' THEN 'Салбар болон Хэрэглэгч'
                 WHEN CC.VALUETYPE = 'BR' THEN 'Салбар болон Эрхийн бүлэг'
                ELSE 'Хэрэглэгч болон Эрхийн бүлэг'
            END AS VALUETYPE_NAME,
            DP.NAME AS ACNT_NAME,
            GU0.NAME AS USER_NAME,
            GU0.ID || ' - ' || GU0.NAME AS USERID_NAME,
            BR.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
            RL.ROLENAME AS ROLE_NAME,
            RL.ID || ' - ' || RL.ROLENAME AS ROLEID_NAME,
            GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
            GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
        FROM
            AD_HIDE CC
            LEFT JOIN GP_INST_USER GU0 ON GU0.ID = CC.USERID
            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
            LEFT JOIN DP_ACCOUNT DP ON DP.ACNTNO = CC.MODULEKEY AND DP.INSTID = CC.INSTID
            LEFT JOIN GP_INST_BRANCH BR ON BR.BRCHNO = CC.BRCHNO AND BR.INSTID = CC.INSTID AND BR.STATUSID = 1
            LEFT JOIN GP_INST_ROLE RL ON RL.ID = CC.ROLEID AND RL.INSTID = CC.INSTID AND RL.STATUSID = 1
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
        DB::statement("DROP VIEW VW_AD_HIDE");
    }
};
