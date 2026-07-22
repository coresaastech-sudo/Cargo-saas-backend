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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_INFO_BUERO");
        Schema::table('ad_credit_info_buero', function (Blueprint $table) {
            $table->string('acntno', 20)->nullable()->comment('Зээлийн дансны дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_credit_info_buero', function (Blueprint $table) {
        });
    }
};
