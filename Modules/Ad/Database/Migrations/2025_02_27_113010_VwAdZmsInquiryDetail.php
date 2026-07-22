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
        DB::statement("DROP VIEW IF EXISTS VW_AD_ZMS_INQUIRY_DETAIL");
        DB::statement(
            "create or replace view vw_ad_zms_inquiry_detail as
                    select
                        zms_inq.id,
                        zms_inq.productno,
                        zms_inq.productname,
                        zms_inq.purptypeid,
                        zms_inq.acnttypeid,
                        zms_inq.custtypeid,
                        zms_inq.custregno,
                        zms_inq.price,
                        zms_inq.fee,
                        zms_inq.fee_acntno,
                        zms_inq.pdf,
                        case
                            when zms_inq.origin = 1 then 'mecore'
                            when zms_inq.origin = 2 then 'melp'
                            else 'unknown'
                        end as origin,
                        zms_inq.created_by || ' - ' || usr.lname || ' ' || usr.name as created_by,
                        zms_inq.instid,
                        zms_inq.stmt_id,
                        inst.name as instname,
                        zms_inq.statusid,
                        case
                            when zms_inq.statusid = 1 then 'амжилттай'
                            else 'амжилтгүй'
                        end as statusidname,
                        zms_inq.created_at
                    from ad_zms_inquiry zms_inq
                    left join GP_inst_user usr on usr.id = zms_inq.created_by
                    left join GP_inst_list inst on inst.id = zms_inq.instid"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW VW_AD_ZMS_INQUIRY_DETAIL");
    }
};
