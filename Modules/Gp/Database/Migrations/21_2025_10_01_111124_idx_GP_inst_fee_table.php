<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up(): void
    {
        // 1) Одоогийн unique constraint-ийг устгана
        DB::statement("
        ALTER TABLE GP_inst_fee_rate
        DROP CONSTRAINT IF EXISTS GP_inst_fee_rate_instid_feecode_intervalno_curcode_statusid_un
    ");
        DB::statement("
            DROP INDEX IF EXISTS GP_inst_fee_rate_instid_feecode_intervalno_curcode_statusid_un
        ");
        DB::statement("
        ALTER TABLE GP_inst_fee_rate
        DROP CONSTRAINT IF EXISTS GP_inst_fee_rate_instid_feecode_intervalno_curcode_unique
    ");
        DB::statement("
            DROP INDEX IF EXISTS GP_inst_fee_rate_instid_feecode_intervalno_curcode_unique
        ");
        // 2) Хуучин constraint-ийг (байвал) устгана
        DB::statement("
            ALTER TABLE GP_inst_fee_cur
            DROP CONSTRAINT IF EXISTS GP_inst_fee_cur_instid_feecode_curcode_statusid_unique
        ");

        // 3) Хэрэв хуучин нь constraint бус unique index байсан бол энд устгана
        DB::statement("
            DROP INDEX IF EXISTS GP_inst_fee_cur_instid_feecode_curcode_statusid_unique
        ");
        // 2) Зөвхөн идэвхтэй мөрүүдэд (statusid = 1) unique мөрдөнө
        DB::statement("
        CREATE UNIQUE INDEX IF NOT EXISTS GP_inst_fee_rate_active_un
        ON GP_inst_fee_rate (instid, feecode, intervalno, curcode)
        WHERE statusid = 1
    ");
        DB::statement("
            CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS GP_inst_fee_cur_active_un
            ON GP_inst_fee_cur (instid, feecode, curcode)
            WHERE statusid = 1
        ");
    }

    public function down(): void
    {
        // Буцаах: partial index-ийг устгаж, хуучин constraint-ийг сэргээх (хэрвээ үнэхээр хэрэгтэй бол)
        DB::statement("
        DROP INDEX IF EXISTS GP_inst_fee_rate_active_un
    ");

        DB::statement("
        ALTER TABLE GP_inst_fee_rate
        ADD CONSTRAINT GP_inst_fee_rate_instid_feecode_intervalno_curcode_statusid_un
        UNIQUE (instid, feecode, intervalno, curcode, statusid)
    ");
    }
};
