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
        DB::statement("DROP VIEW IF EXISTS VW_DICT_GP_CONST_097");
        DB::statement("CREATE OR REPLACE VIEW VW_DICT_GP_CONST_097 AS
                        SELECT ROW_NUMBER() OVER () AS ID,
                            ACNT_CODE || ' - ' || BANK_CODE_NAME as NAME,
                            IBAN AS NAME2,
                            ACNT_CODE || '' AS VALUE,
                            ID AS LISTORDER,
                            CUSTNO AS PARENT_CODE,
                            INSTID
                        FROM VW_CR_CUST_BANK_ACCOUNT
                        ORDER BY ID");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_097");
    }
};
