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
        DB::statement("DROP VIEW IF EXISTS vw_GP_user_act_list");
        DB::statement('CREATE OR REPLACE VIEW vw_GP_user_act_list AS
                        SELECT
                        act.id,
                        act.instid,
                        inst1.name AS instname,
                        act.userid,
                        SUBSTRING(usr1.lname, 1, 1) || \'.\' || usr1.name AS username,
                        act.act_instid,
                        inst2.name AS act_instname,
                        act.act_userid,
                        SUBSTRING(usr2.lname, 1, 1) || \'.\' || usr2.name AS act_username,
                        act.statusid,
                        SUBSTRING(usr3.lname, 1, 1) || \'.\' || usr3.name AS created_name,
                        SUBSTRING(usr4.lname, 1, 1) || \'.\' || usr4.name AS updated_name,
                        act.created_at,
                        act.updated_at
                    FROM GP_user_act_list act
                    LEFT JOIN GP_inst_list inst1 ON inst1.id = act.instid
                    LEFT JOIN GP_inst_list inst2 ON inst2.id = act.act_instid
                    LEFT JOIN GP_inst_user usr1 ON usr1.id = act.userid
                    LEFT JOIN GP_inst_user usr2 ON usr2.id = act.act_userid
                    LEFT JOIN GP_inst_user usr3 ON usr3.id = act.created_by
                    LEFT JOIN GP_inst_user usr4 ON usr4.id = act.updated_by
                    ORDER BY act.id ASC
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS vw_GP_user_act_list");
    }
};
