<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_NOTIFICATION_USERS");
        DB::statement("CREATE OR REPLACE VIEW VW_AD_NOTIFICATION_USERS AS
            (
                SELECT
                    ROW_NUMBER() OVER (ORDER BY CUSTID, TYPE) AS INDEX,
                    FOO.*,
                    INST.NAME AS INSTNAME
                FROM (
                    SELECT 'MEAPP' AS TYPE, 'ME APP' AS TYPE_NAME, APUSER.ID AS CUSTID, APLINK.INSTID AS INSTID,
                           REGNO AS ID1, FIRSTNAME AS FNAME, LASTNAME AS LNAME, EMAIL, PHONE,
                           APUSER.STATUSID, DEVICE_TOKEN
                    FROM AP_CUST_USER APUSER
                    JOIN AP_INST_CUST_USER_LINK APLINK ON APUSER.ID = APLINK.CUST_USERID
                    WHERE APLINK.STATUSID = 1

                    UNION

                    SELECT '0' AS TYPE, 'Харилцагч (Иргэн)' AS TYPE_NAME, ID AS CUSTID, INSTID, ID1,
                           NAME AS FNAME, LNAME, EMAIL, HANDPHONE AS PHONE, STATUSID, '' AS DEVICE_TOKEN
                    FROM CR_CUST_IND

                    UNION

                    SELECT '1' AS TYPE, 'Харилцагч (Байгууллага)' AS TYPE_NAME, ID AS CUSTID, INSTID, ID1,
                           NAME AS FNAME, '' AS LNAME, EMAIL, WORKPHONE AS PHONE, STATUSID, '' AS DEVICE_TOKEN
                    FROM CR_CUST_ORG

                    UNION

                    SELECT 'ADMIN' AS TYPE, 'Админ хэрэглэгч' AS TYPE_NAME, ID AS CUSTID, INSTID, REGNO,
                           NAME AS FNAME, LNAME, EMAIL, PHONE, STATUSID, '' AS DEVICE_TOKEN
                    FROM VW_INST_USER_LIST
                ) FOO
                LEFT JOIN GP_INST_LIST INST ON FOO.INSTID = INST.ID
            )
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_NOTIFICATION_USERS");
    }
};
