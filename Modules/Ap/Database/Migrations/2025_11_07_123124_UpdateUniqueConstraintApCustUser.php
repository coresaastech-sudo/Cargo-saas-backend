<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ap_cust_user хүснэгтийн unique constraint-д app_id нэмэх
     * @return void
     */
    public function up()
    {
        Schema::table('ap_cust_user', function (Blueprint $table) {
            // Хуучин unique constraint-ийг устгах
            $table->dropUnique(['email', 'statusid']);
            
            // Шинэ unique constraint нэмэх (app_id-г оролцуулан)
            $table->unique(['email', 'statusid', 'app_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_cust_user', function (Blueprint $table) {
            // Шинэ unique constraint-ийг устгах
            $table->dropUnique(['email', 'statusid', 'app_id']);
            
            // Хуучин unique constraint сэргээх
            $table->unique(['email', 'statusid']);
        });
    }
};

