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
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_NOTIFICATIONS");
        DB::statement("CREATE OR REPLACE VIEW VW_CR_CUST_NOTIFICATIONS AS
                       SELECT
                            CC.*,
                            CI.NAME,
                            CI.LNAME,
                            CI.ID1,
                            NOTIF.TITLE,
                            NOTIF.DESCRIPTION,
                            NOTIF.URL,
                            NOTIF.NOTIFTYPE,
                            NOTIF.STATUSID AS NOTIFSTATUSID,
                            NOTIF.INSTID AS NOTIFINSTID
                        FROM
                            CR_CUST_NOTIFICATIONS CC
                            LEFT JOIN CR_CUST_IND CI ON CAST(CC.CUSTID AS INT) = CI.ID
                            LEFT JOIN AD_NOTIFICATIONS NOTIF ON CC.NOTIFICATION_ID = NOTIF.ID;

        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_CR_CUST_NOTIFICATIONS");
    }
};
