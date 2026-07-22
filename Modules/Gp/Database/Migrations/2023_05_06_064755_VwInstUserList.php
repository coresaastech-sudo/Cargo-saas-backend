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
        DB::statement("DROP VIEW IF EXISTS VW_INST_USER_LIST CASCADE");
        DB::statement("CREATE OR REPLACE VIEW VW_INST_USER_LIST AS
                        SELECT U.ID,
                            U.INSTID,
                            U.REGNO,
                            U.NAME,
                            U.LNAME,
                            U.BRCHNO,
                            U.EMAIL,
                            U.PHONE,
                            U.USERNAME,
                            U.STATUSID,
                            U.ISADMIN,
                            U.IPREST,
                            U.STARTDATE,
                            BR.NAME AS BRCH_NAME,
                            U.BRCHNO || ' - ' || BR.NAME AS BRCHNO_NAME,
                            IL.NAME AS INST_NAME,
                            IL.NAME2 AS INST_NAME2
                        FROM GP_INST_USER U
                        LEFT JOIN GP_INST_LIST IL ON IL.ID = U.INSTID
                        LEFT JOIN GP_INST_BRANCH BR ON BR.BRCHNO = U.BRCHNO AND BR.INSTID = U.INSTID AND BR.STATUSID = 1
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_INST_USER_LIST");
    }
};
