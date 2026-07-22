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
        Schema::table('ap_acnt_ln', function (Blueprint $table) {
            $table->decimal('debttopay', 23, 5)->nullable()->default(0)->comment('Зээл хэвийн болгох дүн');
            $table->decimal('nowclosebalance', 23, 5)->nullable()->default(0)->comment('Одоо зээл хаах дүн');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_acnt_ln', function (Blueprint $table) {
            $table->dropColumn('debttopay');
            $table->dropColumn('nowclosebalance');
        });
    }
};
