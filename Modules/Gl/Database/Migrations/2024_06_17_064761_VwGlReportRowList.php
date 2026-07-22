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
        DB::statement("DROP VIEW IF EXISTS VW_GL_REPORT_ROW_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GL_REPORT_ROW_LIST AS
                        SELECT CC.*, RC.COLINFO
                        FROM GL_REPORT_CONF_DETAIL CC
                            LEFT JOIN
                            (  SELECT CONF_DETAIL_ID,
                                        STRING_AGG (COLUMNIDX || '-' || COUNT, '; ') AS COLINFO, INSTID
                                    FROM (  SELECT CONF_DETAIL_ID, COLUMNIDX, COUNT (COLUMNIDX) AS COUNT, INSTID
                                            FROM GL_REPORT_CONF_COLUMN
                                            WHERE STATUSID = 1
                                        GROUP BY CONF_DETAIL_ID, COLUMNIDX, INSTID) AS SUBQUERY
                                GROUP BY CONF_DETAIL_ID, INSTID
                                ORDER BY CONF_DETAIL_ID) RC
                                ON RC.CONF_DETAIL_ID = CC.ID AND RC.INSTID = CC.INSTID
                                "
                                );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_GL_REPORT_ROW_LIST");
    }
};
