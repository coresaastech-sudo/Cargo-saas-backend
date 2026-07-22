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
        Schema::table('ap_cust_user', function (Blueprint $table) {
            $table->smallInteger('app_id')->nullable()->comment('Апп дугаар');
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
            $table->smallInteger('app_id')->nullable()->comment('Апп дугаар');
        });
    }
};
