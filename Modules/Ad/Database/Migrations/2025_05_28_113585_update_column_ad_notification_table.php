<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS VW_CR_CUST_NOTIFICATIONS");
        DB::statement("DROP VIEW IF EXISTS VW_AD_NOTIFICATIONS");
        Schema::table('ad_notifications', function (Blueprint $table) {
            $table->text('description')->nullable()->comment('Тайлбар')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_notifications', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->comment('Тайлбар')->change();
        });
    }
};
