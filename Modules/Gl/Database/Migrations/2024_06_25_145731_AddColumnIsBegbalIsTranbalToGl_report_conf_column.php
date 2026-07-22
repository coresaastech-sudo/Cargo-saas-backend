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
        Schema::table('gl_report_conf_column', function (Blueprint $table) {
            $table->smallInteger('isbegbal')->default(0)->comment('Эхний үлдэгдэл бол 1');
            $table->string('istranbal', 4)->nullable()->comment('Гүйлгээ тэнцлээс авах ct - credit, dt - debit, ctdt - цэвэр дүн, cont - байх үед GlRetailEntry - с уг дансны contgl-г авна.');
            $table->unique(['instid', 'statusid', 'conf_detail_id', 'columnidx', 'acntno', 'multiply', 'isbegbal', 'istranbal']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gl_report_conf_column', function (Blueprint $table) {
            $table->dropColumn('isbegbal');
            $table->dropColumn('istranbal');
        });
    }
};
