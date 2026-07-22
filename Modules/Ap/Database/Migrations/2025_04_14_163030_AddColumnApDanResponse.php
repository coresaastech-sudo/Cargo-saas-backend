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
        Schema::table('ap_dan_response', function (Blueprint $table) {
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_dan_response', function (Blueprint $table) {
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
        });
    }
};
