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
            $table->smallInteger('type')->default(0)->comment('Тохиргоо хийгдэж буй төрөл, 0-ЕД дансны дугаараар, 1-Тайлангийн мөрийн дугаараар');
            $table->unique(['instid', 'statusid', 'conf_detail_id', 'type', 'columnidx', 'acntno', 'multiply', 'isbegbal', 'istranbal']);
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
            $table->dropColumn('type');
        });
    }
};
