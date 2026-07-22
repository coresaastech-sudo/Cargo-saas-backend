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
            $table->string('diridcode2', 20)->nullable()->comment('Захирлын улсын бүртгэлийн дугаар маск');
            $table->string('dirid2', 20)->nullable()->comment('Захирлын улсын бүртгэлийн дугаар');
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
            $table->dropColumn('diridcode2');
            $table->dropColumn('dirid2');
        });
    }
};
