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
        Schema::table('re_inst_report_temp_param', function (Blueprint $table) {
            $table->bigInteger('formulaid')->comment('Харгалзах formula id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('re_inst_report_temp_param', function (Blueprint $table) {
            $table->dropColumn('formulaid');
        });
    }
};