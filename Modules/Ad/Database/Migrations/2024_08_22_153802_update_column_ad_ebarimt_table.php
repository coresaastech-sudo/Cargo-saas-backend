<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_EBARIMT");
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            $table->decimal('noncashamount', 23, 8)->nullable()->comment('Бэлэн бус дүн')->change();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            $table->decimal('noncashamount')->nullable()->comment('Бэлэн бус дүн')->change();
        });
    }
};
