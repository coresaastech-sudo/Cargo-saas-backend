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
        DB::statement("DROP VIEW IF EXISTS VW_INST_ROLE_PERM_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_INST_ROLE_PERM_LIST AS
                        SELECT AC.ACTION_CODE,
                                AC.NAME,
                                AC.NAME2,
                                GP.ROLEID,
                                GP.ID
                            FROM GP_INST_ROLE_PERMS GP
                                LEFT JOIN (
                                    SELECT ACTION_CODE,
                                            NAME,
                                            NAME2,
                                            STATUSID
                                        FROM RE_INST_REPORT_TEMP
                                    UNION
                                    SELECT ACTION_CODE,
                                            NAME,
                                            NAME2,
                                            STATUSID
                                    FROM GP_ACTION_CODE
                                ) AC
                                    ON AC.ACTION_CODE = GP.AC
                            WHERE GP.STATUSID = 1 AND AC.STATUSID = 1");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_INST_ROLE_PERM_LIST");
    }
};
