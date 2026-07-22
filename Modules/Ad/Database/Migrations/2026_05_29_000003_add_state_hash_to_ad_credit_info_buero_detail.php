<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Барьцаа / хамтран зээлдэгчийн төлөвийн hash-ийг хадгалж, бодит утга
     * өөрчлөгдсөн эсэхээр (updated_at-аас үл хамааран) шинэчлэх төлөвт оруулах.
     *
     * Хоосон (NULL) бол энэ нь "өмнө нь hash суурилуулагдаагүй legacy бичлэг"
     * гэсэн утгатай — энэ тохиолдолд **flip хийхгүй**, зөвхөн backfill хийнэ.
     * Үгүй бол өмнө нь амжилттай нийлүүлсэн зээлүүд бөөнөөр нь дахин илгээгдэх эрсдэлтэй.
     */
    public function up()
    {
        DB::statement("ALTER TABLE ad_credit_info_buero_detail
            ADD COLUMN IF NOT EXISTS coll_state_hash VARCHAR(40)");
        DB::statement("ALTER TABLE ad_credit_info_buero_detail
            ADD COLUMN IF NOT EXISTS cust_state_hash VARCHAR(40)");

        DB::statement("COMMENT ON COLUMN ad_credit_info_buero_detail.coll_state_hash
            IS 'Сүүлд илгээсэн үеийн барьцаа холбоосын state hash (md5)'");
        DB::statement("COMMENT ON COLUMN ad_credit_info_buero_detail.cust_state_hash
            IS 'Сүүлд илгээсэн үеийн хамтран зээлдэгч холбоосын state hash (md5)'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE ad_credit_info_buero_detail DROP COLUMN IF EXISTS coll_state_hash");
        DB::statement("ALTER TABLE ad_credit_info_buero_detail DROP COLUMN IF EXISTS cust_state_hash");
    }
};
