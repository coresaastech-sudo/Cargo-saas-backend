<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->dropExistingUnique();

        Schema::table('ad_res_account_bal', function (Blueprint $table) {
            $table->unique(
                ['acntno', 'instid', 'statusid', 'resdate', 'rescls', 'clscode', 'amount'],
                'ad_res_account_bal_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_res_account_bal', function (Blueprint $table) {
            $table->dropUnique('ad_res_account_bal_unique');
            $table->unique(['acntno', 'instid', 'statusid', 'resdate', 'rescls', 'clscode']);
        });
    }

    private function dropExistingUnique()
    {
        $constraint = DB::selectOne("
            SELECT c.conname
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = current_schema()
                AND t.relname = 'ad_res_account_bal'
                AND c.contype = 'u'
                AND pg_get_constraintdef(c.oid) LIKE '%UNIQUE (acntno, instid, statusid, resdate, rescls, clscode)%'
            LIMIT 1
        ");

        if ($constraint) {
            DB::statement('ALTER TABLE ad_res_account_bal DROP CONSTRAINT ' . $constraint->conname);
        }
    }
};
