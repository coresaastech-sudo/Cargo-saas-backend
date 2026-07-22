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
        DB::statement(<<<'SQL'
DO $$
DECLARE
    v_instid BIGINT;
    v_anchor SMALLINT;
    v_count INTEGER;
BEGIN
    FOR v_instid IN
        SELECT DISTINCT instid
        FROM GP_inst_eod_steps
    LOOP
        SELECT orderno
          INTO v_anchor
          FROM GP_inst_eod_steps
         WHERE instid = v_instid
           AND function = 'ad800101'
         LIMIT 1;

        SELECT COUNT(*)
          INTO v_count
          FROM GP_inst_eod_steps
         WHERE instid = v_instid
           AND function IN ('ad800066', 'ad800072', 'ad800081');

        IF v_anchor IS NULL OR v_count <> 3 THEN
            CONTINUE;
        END IF;

        IF EXISTS (
            SELECT 1
              FROM GP_inst_eod_steps
             WHERE instid = v_instid
               AND function = 'ad800066'
               AND orderno = v_anchor - 3
        ) AND EXISTS (
            SELECT 1
              FROM GP_inst_eod_steps
             WHERE instid = v_instid
               AND function = 'ad800072'
               AND orderno = v_anchor - 2
        ) AND EXISTS (
            SELECT 1
              FROM GP_inst_eod_steps
             WHERE instid = v_instid
               AND function = 'ad800081'
               AND orderno = v_anchor - 1
        ) THEN
            CONTINUE;
        END IF;

        UPDATE GP_inst_eod_steps
           SET orderno = CASE function
                         WHEN 'ad800066' THEN 30000
                         WHEN 'ad800072' THEN 30001
                         WHEN 'ad800081' THEN 30002
                         END,
               updated_at = NOW()
         WHERE instid = v_instid
           AND function IN ('ad800066', 'ad800072', 'ad800081');

        UPDATE GP_inst_eod_steps
           SET orderno = orderno + 10000,
               updated_at = NOW()
         WHERE instid = v_instid
           AND orderno >= v_anchor
           AND function NOT IN ('ad800066', 'ad800072', 'ad800081');

        UPDATE GP_inst_eod_steps
           SET orderno = orderno - 9997,
               updated_at = NOW()
         WHERE instid = v_instid
           AND orderno >= v_anchor + 10000
           AND orderno < 20000;

        UPDATE GP_inst_eod_steps
           SET orderno = CASE function
                         WHEN 'ad800066' THEN v_anchor
                         WHEN 'ad800072' THEN v_anchor + 1
                         WHEN 'ad800081' THEN v_anchor + 2
                         END,
               updated_at = NOW()
         WHERE instid = v_instid
           AND function IN ('ad800066', 'ad800072', 'ad800081');
    END LOOP;
END $$;
SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This one-time data migration intentionally does not restore institution-specific step orders.
    }
};
