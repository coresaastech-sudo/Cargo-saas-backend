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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_098");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_098 AS
                        SELECT ROW_NUMBER() OVER () AS ID,
                            LM.MORNO || ' - ' || MR.NAME as NAME,
                            MR.NAME AS NAME2,
                            MR.MORNO || '' AS VALUE,
                            MR.MORNO AS LISTORDER,
                            LM.ACNTNO AS PARENT_CODE,
                            MR.INSTID
                        FROM LN_MOR MR
                        LEFT JOIN LN_ACCOUNT_MOR LM ON LM.INSTID = MR.INSTID AND MR.MORNO = LM.MORNO AND LM.STATUSID = 1
                        WHERE MR.STATUSID > 0 AND MR.MORSTATUS > 1
                        ORDER BY MR.MORNO");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_098");
    }
};
