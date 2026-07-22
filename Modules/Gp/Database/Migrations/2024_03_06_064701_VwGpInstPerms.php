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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_PERMS");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_PERMS AS
                        SELECT AC.ACTION_CODE,
                                            AC.NAME,
                                            AC.NAME2,
                                            GP.INSTID,
                                            GP.ISADMIN,
                                            GP.MODULEID,
                                            GP.STATUSID
                                    FROM (
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
                                        LEFT JOIN GP_INST_PERMS GP ON AC.ACTION_CODE = GP.AC
                                    WHERE GP.STATUSID = 1 AND AC.STATUSID = 1");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_PERMS");
    }
};
