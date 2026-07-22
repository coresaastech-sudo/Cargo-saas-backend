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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_BANK_ACCOUNT");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_BANK_ACCOUNT AS
                        SELECT
                            CC.*,
                            GU1.NAME AS CREATED_BY_NAME,
                            GU2.NAME AS UPDATED_BY_NAME,
                            GP1.NAME AS BANK_CODE_NAME
                        FROM
                            CR_CUST_BANK_ACCOUNT CC
                            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                            LEFT JOIN VW_DICT_GP_CONST_063 GP1 ON GP1.VALUE = CAST(CC.BANK_CODE AS VARCHAR) AND GP1.INSTID IN (1, CC.INSTID)
                            ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_BANK_ACCOUNT");
    }
};
