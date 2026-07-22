<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ap_qpay', function (Blueprint $table) {
            $table->string('inquiry_id')->nullable()->comment('Лавлагааны ID (ap_cust_inquiry)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_qpay', function (Blueprint $table) {
            $table->dropColumn('inquiry_id');
        });
    }
};
