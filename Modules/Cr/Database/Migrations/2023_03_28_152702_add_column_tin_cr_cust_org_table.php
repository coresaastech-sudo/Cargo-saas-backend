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
        Schema::table('cr_cust_org', function (Blueprint $table) {
            $table->string('tin', 14)->nullable()->comment('Татвар төлөгчийн дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cr_cust_org', function (Blueprint $table) {
            $table->dropColumn('tin');
        });
    }
};
