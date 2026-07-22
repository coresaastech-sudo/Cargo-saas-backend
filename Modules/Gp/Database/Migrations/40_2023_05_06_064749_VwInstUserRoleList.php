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
        DB::statement("DROP VIEW IF EXISTS VW_INST_USER_ROLE_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_INST_USER_ROLE_LIST AS
                        SELECT
                            U.ID,
                            U.USERID,
                            U.ROLEID,
                            U.INSTID,
                            U.STARTDATE,
                            U.ENDDATE,
                            CASE
                                WHEN U.STATUSID = 1 AND U.ENDDATE < COALESCE(NULLIF(SUBSTR(SEQ.SEQNO, 1, 10), '')::DATE, CURRENT_DATE) THEN 0
                                ELSE U.STATUSID
                            END AS STATUSID,
                            U.CREATED_BY,
                            U.UPDATED_BY,
                            U.CREATED_AT,
                            U.UPDATED_AT,
                            GR.ROLENAME,
                            GR.ROLENAME2
                        FROM GP_INST_USER_ROLES U
                            LEFT JOIN GP_INST_ROLE GR ON GR.ID = U.ROLEID
                            LEFT JOIN GP_INST_SEQ SEQ ON SEQ.INSTID = U.INSTID AND SEQ.SEQID = 'SYSDATE'
                        WHERE U.STATUSID <> -1");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_INST_USER_ROLE_LIST");
    }
};
