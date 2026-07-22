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
            $table->decimal('multiply', 10, 4)->default('1')->comment('Үржигч тоон утга');
            $table->dropUnique('gl_report_conf_column_instid_statusid_conf_detail_id_columnidx_acntno_unique');
            $table->unique(['instid','statusid','conf_detail_id','columnidx','acntno','multiply']);
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
            $table->dropColumn('multiply');
        });
    }
};
