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
        Schema::table('ad_ebarimt_ACTION_CODE', function (Blueprint $table) {
            $table->string('classification_code', 7)->nullable()->default('7113900')->comment('Ангилалын код')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_ebarimt_ACTION_CODE', function (Blueprint $table) {
            $table->dropColumn('classification_code');
        });
    }
};
