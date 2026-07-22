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
            $table->string('use_auth_type', 20)->nullable()->default('EMAIL')->comment('Нэвтрэлт төрөл');
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
            $table->dropColumn('use_auth_type');
        });
    }
};
