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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_EOD_STEP_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_EOD_STEP_LIST AS
                        SELECT ES.NAME,
                                ES.NAME2,
                                ES.ORDERNO,
                                ES.ID,
                                ES.FUNCTION,
                                ES.PROCTYPE,
                                ES.RUNFREQ,
                                ES.RUNDAY,
                                ES.RUNMONTH,
                                ES.STATUSID,
                                ES.MODIFYOPT,
                                ES.INSTID
                        FROM GP_INST_EOD_STEPS ES");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_EOD_STEP_LIST");
    }
};
