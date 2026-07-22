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
        DB::statement("DROP VIEW IF EXISTS VW_GP_CONN_CONF_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_CONN_CONF_LIST AS
                        SELECT CC.*,
                                DIC.NAME AS TYPEID_NAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM GP_CONN_CONF CC
                        LEFT JOIN VW_DICT_GP_CONST_058 DIC ON DIC.VALUE = CC.TYPEID
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_CONN_CONF_LIST");
    }
};
