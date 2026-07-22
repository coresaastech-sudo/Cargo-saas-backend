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
        Schema::table('ap_cust_contracts', function (Blueprint $table) {
            $table->bigInteger('sign_image_id')->nullable()->comment('Гэрээн дээрх гарын үсэг дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_cust_contracts', function (Blueprint $table) {
            $table->dropColumn('sign_image_id');
        });
    }
};
