<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            // Одоогийн unique constraint-ийг устгах
            $table->dropUnique(['instid', 'txndate', 'jrno', 'statusid']);
            
            // amount болон res_success-тай шинэ unique constraint нэмэх
            $table->unique(['instid', 'txndate', 'jrno', 'statusid', 'amount'], 'ad_ebarimt_unique');
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
            // Шинэ unique constraint-ийг устгах
            $table->dropUnique('ad_ebarimt_unique');
            
            // Анхны unique constraint-ийг сэргээх
            $table->unique(['instid', 'txndate', 'jrno', 'statusid']);
        });
    }
};
