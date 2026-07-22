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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_ADDRESS");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_ADDRESS AS
                        SELECT
                            CC.*,
                            GU1.NAME AS CREATED_BY_NAME,
                            GU2.NAME AS UPDATED_BY_NAME,
                            GP1.NAME AS STATE_NAME,
                            GP2.NAME AS REGION_NAME,
                            GP3.NAME AS SUBREGION_NAME
                        FROM
                            cr_cust_address CC
                            LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                            LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                            LEFT JOIN VW_DICT_GP_CONST_001 GP1 ON GP1.VALUE = CAST(CC.STATE AS VARCHAR)
                            LEFT JOIN VW_DICT_GP_CONST_002 GP2 ON GP2.VALUE= CAST(CC.REGION AS VARCHAR)
                            LEFT JOIN VW_DICT_GP_CONST_002 GP3 ON GP3.VALUE= CAST(CC.SUBREGION AS VARCHAR);
                            ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_ADDRESS");
    }
};
