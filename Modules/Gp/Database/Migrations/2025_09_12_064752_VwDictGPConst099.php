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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_099");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_099 AS
                        SELECT ROW_NUMBER() OVER () AS ID,
                            CC.VOSTROACNTNO || ' - ' || CON.NAME AS NAME,
                            CON.NAME AS NAME2,
                            CC.VOSTROACNTNO AS VALUE,
                            CC.BANKCODE AS LISTORDER,
                            CC.INSTID AS PARENT_CODE,
                            CC.INSTID
                        FROM IA_ACCOUNT CC
                        LEFT JOIN GP_CONST CON ON CON.PARENT_CODE = 'bank' AND CC.BANKCODE = CON.VALUE
                        WHERE CC.STATUSID > 0 AND CC.BANKCODE IS NOT NULL AND CC.VOSTROACNTNO IS NOT NULL
                        ORDER BY CC.BANKCODE");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_099");
    }
};
