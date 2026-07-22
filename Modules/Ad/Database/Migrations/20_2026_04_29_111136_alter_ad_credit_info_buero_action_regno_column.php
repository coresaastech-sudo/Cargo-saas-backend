<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ad_credit_info_buero_action', function (Blueprint $table) {
            $table->string('regno', 20)->change();
        });
    }

    public function down()
    {
        Schema::table('ad_credit_info_buero_action', function (Blueprint $table) {
            $table->string('regno', 10)->change();
        });
    }
};
