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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_019");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_019 AS
                        SELECT
                                    ROW_NUMBER() OVER () AS ID,
                                    ACTION_CODE AS VALUE,
                                    NAME,
                                    NAME2,
                                    TXNTYPE,
                                    MODULEID,
                                    TXNOPT,
                                    QUALIFIER,
                                    1 LISTORDER,
                                    STATUSID,
                                    1 INSTID
                            FROM GP_ACTION_CODE AC
                            WHERE AC.QUALIFIER=1 AND AC.MODULEID='ln' AND AC.ACTION_CODE IN ('ln902041', 'ln902042', 'ln902043')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_019");
    }
};
