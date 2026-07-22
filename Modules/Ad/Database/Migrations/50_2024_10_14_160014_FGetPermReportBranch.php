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
            "CREATE OR REPLACE FUNCTION get_perm_report_branch(vpc VARCHAR, vuserid INT)
                RETURNS TABLE (showbrchno VARCHAR)
                AS $$
                DECLARE
                    user_instid INT;
                    user_brchno VARCHAR;
                    txndate VARCHAR;
                BEGIN
                    -- Хэрэглэгчийн мэдээлэл авах
                    SELECT instid, a.brchno
                    INTO user_instid, user_brchno
                    FROM GP_inst_user a
                    WHERE id = vuserid;

                    -- Системийн огноо авах
                    SELECT seqno::date
                    INTO txndate
                    FROM GP_inst_seq a
                    WHERE seqid = 'SYSDATE' AND instid = user_instid;

                    -- AdPermReport байгаа эсэхийг шалгах
                    IF EXISTS (
                        SELECT 1
                        FROM ad_perm_report
                        WHERE instid = user_instid AND statusid = 1 AND AC = vpc
                    ) THEN

                        -- Нөхцөлд таарах branch жагсаалт буцаах
                        RETURN QUERY
                        SELECT DISTINCT
                            CASE
                                WHEN a.showbrchno = 'ALL' THEN b.brchno
                                ELSE a.showbrchno
                            END AS showbrchno
                        FROM ad_perm_report a
                        LEFT JOIN GP_inst_branch b
                            ON b.instid = user_instid AND b.statusid = 1
                        WHERE (
                            (a.userid = vuserid AND a.valuetype = 'U' AND a.AC = vpc AND a.statusid = 1)
                            OR
                            (a.brchno = user_brchno AND a.valuetype = 'B' AND a.instid = user_instid AND a.AC = vpc AND a.statusid = 1)
                            OR
                            (
                                a.roleid IN (
                                    SELECT roleid
                                    FROM GP_inst_user_roles
                                    WHERE userid = vuserid
                                    AND instid = user_instid
                                    AND statusid = 1
                                    AND startdate <= txndate::date
                                    AND enddate >= txndate::date
                                )
                                AND a.valuetype = 'R'
                                AND a.instid = user_instid
                                AND a.AC = vpc
                                AND a.statusid = 1
                            )
                        );

                    ELSE
                        -- AdPermReport байхгүй бол салбаруудыг бүгдийг нь буцаах
                        RETURN QUERY
                        SELECT a.brchno AS showbrchno
                        FROM GP_inst_branch a
                        WHERE a.instid = user_instid AND a.statusid = 1;
                    END IF;

                END;
                $$ LANGUAGE plpgsql;
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
        DB::statement("DROP FUNCTION GET_PERM_REPORT_BRANCH");
    }
};
