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
        DB::statement("CREATE OR REPLACE FUNCTION GETBINTDUEDATE(
                                            pacntno VARCHAR,
                                            pdue    NUMERIC,
                                            pnow    DATE,
                                            pinstid NUMERIC
                                            )
                        RETURNS DATE
                        AS $$
                        DECLARE
                            vprevtrandate   DATE;
                            vtrandate       DATE;
                            vintdue         NUMERIC(19, 5);
                            vchgint         NUMERIC(19, 5);
                            vduedate        DATE;
                            vcount          INTEGER;
                            vfirstloop      INTEGER;
                        BEGIN
                        vintdue := pdue;
                        vprevtrandate := pnow;
                        vcount := 0;
                        vfirstloop := 1;

                        IF vintdue > 0 THEN
                            FOR vtrandate, vchgint IN
                            select txndate, SUM(txnamount) AS chgint
                                from (
                                SELECT txndate, SUM(txnamount) * -1 AS txnamount
                                    FROM ln_txn
                                    WHERE acntno = pacntno
                                        AND txndate <= pnow
                                        AND txncode = 'ln902044'
                                        AND COALESCE(promo, '') = 'CT'
                                        AND COALESCE(txndef, '') NOT IN ('00', '99')
                                        AND txnamount > 0
                                        AND instid = pinstid
                                        AND corr != 1
                                    GROUP BY txndate
                                UNION
                                SELECT txndate, SUM(txnamount) AS txnamount
                                    FROM ln_txn
                                    WHERE acntno = pacntno
                                        AND txndate <= pnow
                                        AND txncode IN ('ln902051', 'ln902054')
                                        AND parenttxncode NOT IN ('ln800016', 'ln800017')
                                        AND COALESCE(promo, '') <> 'CT'
                                        AND COALESCE(txndef, '') NOT IN ('00', '99')
                                        AND instid = pinstid
                                        AND corr != 1
                                        GROUP BY txndate
                                UNION
                                SELECT txndate, SUM(txnamount) AS txnamount
                                    FROM ln_txn
                                    WHERE acntno = pacntno
                                        AND txndate <= pnow
                                        AND txncode = 'ln802041'
                                        AND COALESCE(promo, '') = 'CT'
                                        AND COALESCE(txndef, '') NOT IN ('00', '99')
                                        AND txnamount > 0
                                        AND instid = pinstid
                                        AND corr != 1
                                    GROUP BY txndate
                                UNION
                                SELECT txndate, SUM(txnamount) * -1 AS txnamount
                                    FROM ln_txn
                                    WHERE acntno = pacntno
                                        AND txndate <= pnow
                                        AND txncode = 'ln902044'
                                        AND parenttxncode = 'ln800005'
                                        AND COALESCE(promo, '') = 'CT'
                                        AND COALESCE(txndef, '') NOT IN ('00', '99')
                                        AND txnamount < 0
                                        AND instid = pinstid
                                        AND corr != 1
                                    GROUP BY txndate
                                UNION
                                SELECT txndate, SUM(txnamount) AS txnamount
                                    FROM ln_txn
                                    WHERE acntno = pacntno
                                        AND txndate <= pnow
                                        AND txncode = 'ln902054'
                                        AND parenttxncode = 'ln800005'
                                        AND COALESCE(promo, '') = 'CT'
                                        AND COALESCE(txndef, '') NOT IN ('00', '99')
                                        AND txnamount < 0
                                        AND instid = pinstid
                                        AND corr != 1
                                    GROUP BY txndate
                             ) a
                            GROUP BY txndate
                            ORDER BY txndate DESC
                            LOOP
                                IF vintdue - vchgint <= 0 THEN
                                    vcount := vcount + 1;

                                    IF vcount <= 1 OR vfirstloop = 1 THEN
                                        vintdue := vintdue - vchgint;
                                        vprevtrandate := vtrandate;
                                        vfirstloop := vfirstloop + 1;

                                        IF vintdue <= 0 THEN
                                            EXIT;
                                        END IF;
                                    ELSE
                                        EXIT;
                                    END IF;
                                ELSE
                                    vintdue := vintdue - vchgint;
                                    vprevtrandate := vtrandate;
                                END IF;
                            END LOOP;
                        END IF;

                        RETURN vprevtrandate;
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
        DB::statement("DROP FUNCTION GETBINTDUEDATE");
    }
};
