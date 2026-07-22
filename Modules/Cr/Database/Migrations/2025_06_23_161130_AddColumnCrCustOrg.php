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
            $table->unsignedTinyInteger('email_verified')->default(0)->comment('Имэйл хаяг баталгаажуулсан эсэх');
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
            $table->dropColumn('email_verified');
        });
    }
};
