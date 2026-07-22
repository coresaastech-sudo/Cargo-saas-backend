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
        DB::statement("DROP VIEW IF EXISTS VW_GL_ACNT_CLASS_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GL_ACNT_CLASS_LIST AS
                        SELECT CC.*,
                                DIC.NAME AS TYPE_NAME,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                            FROM GL_ACCOUNT_CLASS CC
                                LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                                LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                                LEFT JOIN VW_DICT_GP_CONST_056 DIC ON DIC.VALUE = CC.TYPE"
                                );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GL_ACNT_CLASS_LIST");
    }
};
