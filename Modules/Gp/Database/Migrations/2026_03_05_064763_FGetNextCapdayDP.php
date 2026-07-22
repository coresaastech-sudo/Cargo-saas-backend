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
        DB::statement("CREATE OR REPLACE FUNCTION fn_dp_nextcapday(
                                                        p_sysdate      date,
                                                        p_termstart    date,
                                                        p_termexp      date,
                                                        p_crcapday     VARCHAR,   -- 'E','M','A'
                                                        p_crcapfreq    VARCHAR,   -- 'M','Q','B','Y','P'
                                                        p_cracapday    numeric    -- account day (for 'A'), can be null/0
                                                    ) RETURNS date
                                                    LANGUAGE plpgsql
                                                    IMMUTABLE
                                                    AS $$
                                                    DECLARE
                                                        -- shared
                                                        v_candidate    date;

                                                        -- for M (anniversary)
                                                        v_months_diff  int;
                                                        v_step         int;     -- months step: M=1, Q=3, B=6, Y=12
                                                        v_k            int;     -- base months multiple of step
                                                        v_anchor       date;
                                                        v_day          int := EXTRACT(day FROM p_termstart)::int;

                                                        -- for A/E (period-based)
                                                        v_workdate     date;
                                                        v_period_start date;
                                                        v_period_end   date;
                                                        v_period_len   int;
                                                        v_capday       int;

                                                        -- safety
                                                        v_iter         int;
                                                    BEGIN
                                                        -- already past maturity => no next cap day
                                                        IF p_sysdate > p_termexp THEN
                                                            RETURN NULL;
                                                        END IF;

                                                        IF p_termstart > p_sysdate THEN
                                                            RETURN NULL;
                                                        END IF;

                                                        -- P = хугацааны эцэст => termexpdate
                                                        IF p_crcapfreq = 'P' THEN
                                                            RETURN p_termexp;
                                                        END IF;

                                                        -------------------------------------------------------------------
                                                        -- M = Anniversary => NEXT cap day (>= sysdate)
                                                        -- Overflow rule: if day(termstart) doesn't exist in anchor month,
                                                        --                use next month's 1st day (month_end + 1 day).
                                                        -------------------------------------------------------------------
                                                        IF p_crcapday = 'M' THEN
                                                            v_months_diff :=
                                                                (date_part('year', age(p_sysdate, p_termstart))::int * 12)
                                                            +  date_part('month', age(p_sysdate, p_termstart))::int;

                                                            v_step :=
                                                                CASE p_crcapfreq
                                                                    WHEN 'M' THEN 1
                                                                    WHEN 'Q' THEN 3
                                                                    WHEN 'B' THEN 6
                                                                    WHEN 'Y' THEN 12
                                                                    ELSE NULL
                                                                END;

                                                            IF v_step IS NULL THEN
                                                                RETURN NULL;
                                                            END IF;

                                                            v_k := v_step * (v_months_diff / v_step);

                                                            v_iter := 0;
                                                            LOOP
                                                                v_iter := v_iter + 1;
                                                                IF v_iter > 2400 THEN
                                                                    RETURN NULL;
                                                                END IF;

                                                                v_anchor := (p_termstart + make_interval(months => v_k))::date;

                                                                -- anchor сарын сүүл
                                                                v_period_end := (date_trunc('month', v_anchor)::date + interval '1 month - 1 day')::date;

                                                                IF v_day > EXTRACT(day FROM v_period_end)::int THEN
                                                                    -- overflow: дараагийн сарын 1
                                                                    v_candidate := (date_trunc('month', v_anchor)::date + interval '1 month')::date;
                                                                ELSE
                                                                    v_candidate := (date_trunc('month', v_anchor)::date + (v_day - 1) * interval '1 day')::date;
                                                                END IF;

                                                                IF v_candidate >= p_sysdate THEN
                                                                    RETURN LEAST(v_candidate, p_termexp);
                                                                END IF;

                                                                v_k := v_k + v_step;
                                                            END LOOP;
                                                        END IF;

                                                        -------------------------------------------------------------------
                                                        -- A = Account day within period => NEXT cap day (>= sysdate)
                                                        -- Period start + (cracapday-1), clamp by period length.
                                                        -------------------------------------------------------------------
                                                        IF p_crcapday = 'A' THEN
                                                            v_capday := GREATEST(COALESCE(NULLIF(p_cracapday, 0), 1), 1);
                                                            v_workdate := p_sysdate;

                                                            v_iter := 0;
                                                            LOOP
                                                                v_iter := v_iter + 1;
                                                                IF v_iter > 2400 THEN
                                                                    RETURN NULL;
                                                                END IF;

                                                                -- if we've moved beyond maturity, cap at maturity
                                                                IF v_workdate > p_termexp THEN
                                                                    RETURN p_termexp;
                                                                END IF;

                                                                IF p_crcapfreq = 'M' THEN
                                                                    v_period_start := date_trunc('month', v_workdate)::date;
                                                                    v_period_end   := (v_period_start + interval '1 month - 1 day')::date;

                                                                ELSIF p_crcapfreq = 'Q' THEN
                                                                    v_period_start := date_trunc('quarter', v_workdate)::date;
                                                                    v_period_end   := (v_period_start + interval '3 months - 1 day')::date;

                                                                ELSIF p_crcapfreq = 'B' THEN
                                                                    v_period_start :=
                                                                        (CASE WHEN EXTRACT(month FROM v_workdate) <= 6
                                                                            THEN date_trunc('year', v_workdate)
                                                                            ELSE date_trunc('year', v_workdate) + interval '6 months'
                                                                        END)::date;
                                                                    v_period_end := (v_period_start + interval '6 months - 1 day')::date;

                                                                ELSIF p_crcapfreq = 'Y' THEN
                                                                    v_period_start := date_trunc('year', v_workdate)::date;
                                                                    v_period_end   := (v_period_start + interval '1 year - 1 day')::date;

                                                                ELSE
                                                                    RETURN NULL;
                                                                END IF;

                                                                v_period_len := (v_period_end - v_period_start + 1);
                                                                v_candidate := (v_period_start + (LEAST(v_capday, v_period_len) - 1) * interval '1 day')::date;

                                                                IF v_candidate >= p_sysdate THEN
                                                                    RETURN LEAST(v_candidate, p_termexp);
                                                                END IF;

                                                                -- advance to next period
                                                                v_workdate := (v_period_end + 1);
                                                            END LOOP;
                                                        END IF;

                                                        -------------------------------------------------------------------
                                                        -- E = Period end => NEXT period end (>= sysdate)
                                                        -------------------------------------------------------------------
                                                        IF p_crcapday = 'E' THEN
                                                            v_workdate := p_sysdate;

                                                            v_iter := 0;
                                                            LOOP
                                                                v_iter := v_iter + 1;
                                                                IF v_iter > 2400 THEN
                                                                    RETURN NULL;
                                                                END IF;

                                                                IF v_workdate > p_termexp THEN
                                                                    RETURN p_termexp;
                                                                END IF;

                                                                IF p_crcapfreq = 'M' THEN
                                                                    v_candidate := (date_trunc('month', v_workdate)::date + interval '1 month - 1 day')::date;

                                                                ELSIF p_crcapfreq = 'Q' THEN
                                                                    v_candidate := (date_trunc('quarter', v_workdate)::date + interval '3 months - 1 day')::date;

                                                                ELSIF p_crcapfreq = 'B' THEN
                                                                    v_candidate :=
                                                                        (CASE
                                                                            WHEN EXTRACT(month FROM v_workdate) <= 6
                                                                                THEN (date_trunc('year', v_workdate) + interval '6 months - 1 day')::date
                                                                            ELSE (date_trunc('year', v_workdate) + interval '1 year - 1 day')::date
                                                                        END);

                                                                ELSIF p_crcapfreq = 'Y' THEN
                                                                    v_candidate := (date_trunc('year', v_workdate)::date + interval '1 year - 1 day')::date;

                                                                ELSE
                                                                    RETURN NULL;
                                                                END IF;

                                                                IF v_candidate >= p_sysdate THEN
                                                                    RETURN LEAST(v_candidate, p_termexp);
                                                                END IF;

                                                                v_workdate := (v_candidate + 1);
                                                            END LOOP;
                                                        END IF;

                                                        RETURN NULL;
                                                    END;
                                                    $$;
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
