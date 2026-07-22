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
        Schema::table('ap_negdi', function (Blueprint $table) {
            $table->string('ordernum', 20)->nullable()->comment('Захиалгын дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_negdi', function (Blueprint $table) {
            $table->dropColumn('ordernum');
        });
    }
};
