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
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_QUAL_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_QUAL_LIST AS
                        SELECT QU.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME,
                                DIC.NAME AS CLSCODE_NAME,
                                AC.NAME AS TXNCODE_NAME
                        FROM GP_INST_QUAL QU
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = QU.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = QU.UPDATED_BY
                        LEFT JOIN VW_DICT_GP_CONST_036 DIC ON DIC.VALUE = CAST(QU.CLSCODE AS VARCHAR)
                        LEFT JOIN GP_ACTION_CODE AC ON AC.ACTION_CODE = QU.TXNCODE
                        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GP_INST_QUAL_LIST");
    }
};
