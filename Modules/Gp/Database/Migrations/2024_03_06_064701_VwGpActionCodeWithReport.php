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
        DB::statement("DROP VIEW IF EXISTS VW_GP_ActionCode_WITH_REPORT");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_ActionCode_WITH_REPORT AS
                        SELECT *
                            FROM ( (SELECT ACTION_CODE,
                                            NAME,
                                            NAME2,
                                            STATUSID,
                                            '' AS CONTROLLER,
                                            '' AS FUNCTION,
                                            NULL AS TXNTYPE,
                                            ACTION_CODE AS ID
                                        FROM RE_INST_REPORT_TEMP
                                    WHERE statusid = 1)
                                    UNION
                                    (SELECT ACTION_CODE,
                                            NAME,
                                            NAME2,
                                            STATUSID,
                                            CONTROLLER,
                                            FUNCTION,
                                            TXNTYPE,
                                            ACTION_CODE AS ID
                                    FROM GP_ACTION_CODE
                                    WHERE STATUSID = 1)) AS ACTION_CODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_ActionCode_WITH_REPORT");
    }
};
