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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_DOC_TEMP_ActionCode");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_DOC_TEMP_ActionCode AS
                        SELECT  DPC.*,
                                DT.ID || ' - ' || DT.NAME AS DOCTEMPID_NAME,
                                DT.NAME AS DOCTEMP_NAME,
                                AC.ACTION_CODE || ' - ' || AC.NAME AS ACTION_CODE_NAME,
                                AC.NAME AS PROCESS_NAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM GP_INST_DOC_TEMP_ACTION_CODE DPC
                        LEFT JOIN GP_ACTION_CODE AC ON AC.ACTION_CODE = DPC.ACTION_CODE
                        LEFT JOIN GP_INST_DOC_TEMP DT ON DT.ID = DPC.DOCTEMPID AND DT.INSTID = DPC.INSTID
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = DPC.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = DPC.UPDATED_BY");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_DOC_TEMP_ActionCode");
    }
};
