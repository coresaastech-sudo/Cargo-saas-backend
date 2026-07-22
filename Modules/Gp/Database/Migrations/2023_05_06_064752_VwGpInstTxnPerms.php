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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_TXN_PERMS");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_TXN_PERMS AS
                        SELECT AC.ACTION_CODE,
                            AC.NAME,
                            AC.NAME2,
                            AC.TXNOPT,
                            AC.QUALIFIER,
                            AC.TXNTYPE,
                            AC.ACNTTYPE1,
                            AC.ACNTNO1,
                            AC.ACNTTYPE2,
                            AC.ACNTNO2,
                            GP.INSTID,
                            AC.MODULEID,
                            GP.ID
                        FROM GP_ACTION_CODE AC
                        LEFT JOIN GP_INST_PERMS GP ON AC.ACTION_CODE = GP.AC
                        WHERE GP.STATUSID = 1 AND AC.STATUSID = 1 AND AC.MODULEID IN ('dp', 'ln', 'ia', 'ca', 'tr')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_TXN_PERMS");
    }
};
