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
        DB::statement(
            "CREATE OR REPLACE FUNCTION get_perm_report_manager(
                p_userid INTEGER,
                p_pc     TEXT,
                la       ln_account
            )
                RETURNS BOOLEAN
                    LANGUAGE plpgsql
                    AS $$
                    DECLARE
                        v_s INT;
                        v_c INT;
                        v_a INT;
                        v_m INT;
                    BEGIN
                        SELECT
                            MAX(CASE WHEN a.valuetype = 'S' THEN 1 ELSE 0 END),
                            MAX(CASE WHEN a.valuetype = 'C' THEN 1 ELSE 0 END),
                            MAX(CASE WHEN a.valuetype = 'A' THEN 1 ELSE 0 END),
                            MAX(CASE WHEN a.valuetype = 'M' THEN 1 ELSE 0 END)
                        INTO v_s, v_c, v_a, v_m
                        FROM ad_perm_report a
                        WHERE a.AC = p_pc
                        AND a.statusid = 1
                        AND a.userid = p_userid
                        AND a.valuetype IN ('S','C','A','M');

                        RETURN
                        (v_s = 0 AND v_c = 0 AND v_a = 0 AND v_m = 0)
                        OR (v_s = 1 AND la.sellermanager = p_userid)
                        OR (v_c = 1 AND la.auditmanager = p_userid)
                        OR (v_a = 1 AND la.analysismanager = p_userid)
                        OR (v_m = 1 AND la.riskmanager = p_userid);
                    END;
                $$;
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
        DB::statement("DROP FUNCTION get_perm_report_manager");
    }
};
