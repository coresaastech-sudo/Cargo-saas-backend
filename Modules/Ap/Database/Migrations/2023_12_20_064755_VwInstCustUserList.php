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
        DB::statement("DROP VIEW IF EXISTS VW_AP_INST_CUST_USER_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_AP_INST_CUST_USER_LIST AS
                        SELECT  U.ID,
                                LNK.INSTID,
                                U.REGNO,
                                U.LASTNAME,
                                U.FIRSTNAME,
                                U.EMAIL,
                                LNK.CREATED_AT,
                                LNK.STATUSID
                        FROM AP_INST_CUST_USER_LINK LNK
                        LEFT JOIN AP_CUST_USER U ON U.ID = LNK.CUST_USERID
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AP_INST_CUST_USER_LIST");
    }
};
