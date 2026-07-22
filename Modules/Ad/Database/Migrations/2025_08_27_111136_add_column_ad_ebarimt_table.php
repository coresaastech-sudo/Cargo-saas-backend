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
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            $table->string('ebarimt_consumerNo', 12)->nullable()->comment('Иргэний ebarimt-н бүртгэлийн дугаар');
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
            $table->dropColumn('ebarimt_consumerNo');
        });
    }
};
