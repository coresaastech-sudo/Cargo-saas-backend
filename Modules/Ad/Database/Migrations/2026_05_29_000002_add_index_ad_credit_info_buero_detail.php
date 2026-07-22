<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY нь transaction дотор ажиллахгүй тул migration-г
     * transaction-гүй ажиллуулна (том хүснэгт дээр бичих түгжээ тавихгүй).
     */
    public $withinTransaction = false;

    /**
     * generateBueroDetails дахь "өөрчлөгдсөн зээл" илрүүлэх (view vs detail diff)
     * болон pending сонголтын query-г хурдасгах индексүүд.
     */
    public function up()
    {
        // (instid, statusid, acntno) — statusid-аар шүүж acntno авах/join хийхэд
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_buero_detail_inst_status_acnt
            ON ad_credit_info_buero_detail (instid, statusid, acntno)");

        // (instid, acntno) — view-той acntno-гоор join хийхэд
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_buero_detail_inst_acnt
            ON ad_credit_info_buero_detail (instid, acntno)");

        // ad_credit_info_buero_action: correctBueroActions / хуваарийн цэвэрлэгээний
        // (type, parent_key, regno, instid, statusid) шүүлтийг хурдасгана
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_buero_action_type_parent_regno
            ON ad_credit_info_buero_action (instid, type, parent_key, regno, statusid)");
    }

    public function down()
    {
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS idx_buero_detail_inst_status_acnt");
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS idx_buero_detail_inst_acnt");
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS idx_buero_action_type_parent_regno");
    }
};
