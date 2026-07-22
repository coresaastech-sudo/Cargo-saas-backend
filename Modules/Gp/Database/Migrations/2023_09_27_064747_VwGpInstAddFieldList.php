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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_ADD_FIELD_LIST");
        DB::unprepared("CREATE OR REPLACE VIEW VW_GP_INST_ADD_FIELD_LIST AS
                        SELECT AF.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                DIC.NAME AS TYPECODE_NAME
                        FROM GP_INST_ADD_FIELD AF
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = AF.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = AF.UPDATED_BY
                        LEFT JOIN (
                            SELECT ML.MODULEID, ML.MODULEID || ' - ' || ML.NAME AS NAME
                                FROM GP_MODULE_LIST ML
                            WHERE ML.PARENTID IS NULL AND ML.TYPEID = '1'
                        ) DIC ON DIC.MODULEID = CAST(AF.TYPECODE AS VARCHAR)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_ADD_FIELD_LIST");
    }
};
