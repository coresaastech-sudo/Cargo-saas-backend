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
        DB::statement("DROP VIEW IF EXISTS VW_AD_ACNT_HIDE");
        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_ACNT_HIDE AS
                WITH UserData AS (
                    SELECT instid, brchno, id AS userid
                    FROM GP_inst_user
                    WHERE statusid = 1
                ), UserRole AS (
                    SELECT roleid, userid, instid
                    FROM GP_inst_user_roles
                    WHERE statusid = 1
                ), HideRules AS (
                    SELECT *
                    FROM ad_hide
                    WHERE statusid = 1
                )
                SELECT
                    H.modulekey,
                    H.valuetype,
                    U.userid,
                    U.instid,
                    CASE
                        WHEN H.valuetype = 'U' AND (H.userid = U.userid) THEN TRUE
                        WHEN H.valuetype = 'B' AND (H.brchno = U.brchno) THEN TRUE
                        WHEN H.valuetype = 'R' AND (H.roleid = R.roleid) THEN TRUE
                        WHEN H.valuetype = 'BU' AND (H.userid = U.userid OR H.brchno = U.brchno) THEN TRUE
                        WHEN H.valuetype = 'UR' AND (H.userid = U.userid OR H.roleid = R.roleid) THEN TRUE
                        WHEN H.valuetype = 'BR' AND (H.brchno = U.brchno OR H.roleid = R.roleid) THEN TRUE
                        ELSE FALSE
                    END AS should_show
                FROM HideRules H
                JOIN UserData U ON H.instid = U.instid
                LEFT JOIN UserRole R ON U.userid = R.userid AND U.instid = R.instid"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_ACNT_HIDE");
    }
};
