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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_DOC_TEMP_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_DOC_TEMP_LIST AS
                        SELECT  DT.ID,
                                DT.NAME,
                                DT.NAME2,
                                DT.STATUSID,
                                DT.DOCTYPE,
                                DT.INSTID
                        FROM GP_INST_DOC_TEMP DT");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_DOC_TEMP_LIST");
    }
};
