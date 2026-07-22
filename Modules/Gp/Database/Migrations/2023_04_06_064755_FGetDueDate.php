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
        DB::statement("CREATE OR REPLACE FUNCTION GETDUEDATE (
            pacntno VARCHAR,
            pdue NUMERIC,
            pnow DATE,
            pinstid NUMERIC
        )
        RETURNS DATE AS $$
        DECLARE
            vpayday DATE;
            vpayamount NUMERIC(19, 5);
            vremain NUMERIC(19, 5);
            vduedate DATE;
        BEGIN
            vremain := pdue;
            vduedate := pnow;

            IF pdue > 0 THEN
                FOR vpayday, vpayamount IN
                    SELECT payday, payamount - intamount as payamount
                    FROM ln_schd
                    WHERE acntno = pacntno AND payday <= pnow AND instid = pinstid
                    ORDER BY payday DESC
                LOOP
                    vduedate := vpayday;
                    IF vremain - vpayamount < 0.019 THEN
                        EXIT;
                    ELSE
                        vremain := vremain - vpayamount;
                    END IF;
                END LOOP;
            END IF;

            RETURN (DATE_TRUNC('day', vduedate));
        END;
        $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP FUNCTION GETDUEDATE");
    }
};
