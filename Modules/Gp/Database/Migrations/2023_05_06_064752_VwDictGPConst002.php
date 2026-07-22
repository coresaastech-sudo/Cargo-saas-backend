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
    DB::statement("
                    CREATE OR REPLACE VIEW VW_DICT_GP_CONST_002 AS
                    SELECT
                        t.ID,
                        t.display_name AS NAME,   -- илгээх NAME
                        t.NAME2,
                        t.VALUE,
                        t.LISTORDER,
                        t.INSTID,
                        t.PARENT_CODE
                    FROM (
                        SELECT
                            ID,
                            value || ' - ' || NAME AS display_name,  -- илгээх нэр
                            NAME,                                    -- жинхэнэ name (эрэмбэ хийх)
                            NAME2,
                            VALUE,
                            LISTORDER,
                            INSTID,
                            PARENT_CODE
                        FROM GP_CONST
                        WHERE STATUSID = 1
                        AND PARENT_CODE IS NOT NULL
                        AND IS_SHOW_FRONT = 1
                    ) t
                    ORDER BY t.NAME     -- зөвхөн жинхэнэ name-р эрэмбэлнэ
    ");
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_DICT_GP_CONST_002");
    }
};
