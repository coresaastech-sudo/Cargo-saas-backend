<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CORPORATE_GATEWAY");
        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->comment('Гүйлгээний шалтгаан')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            $table->string('reason', 50)->nullable()->comment('Гүйлгээний шалтгаан')->change();
        });
    }
};
