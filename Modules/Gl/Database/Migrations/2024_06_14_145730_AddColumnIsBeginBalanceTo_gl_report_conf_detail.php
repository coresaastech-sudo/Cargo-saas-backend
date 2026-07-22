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
        Schema::table('gl_report_conf_detail', function (Blueprint $table) {
            $table->smallInteger('isbegbal')->default(0)->comment('Эхний үлдэгдэл бол 1');
           });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gl_report_conf_detail', function (Blueprint $table) {
            $table->dropColumn('isbegbal');
        });
    }
};
