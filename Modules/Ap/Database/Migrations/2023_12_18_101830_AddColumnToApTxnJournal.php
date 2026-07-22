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
        Schema::table('ap_txn_journal', function (Blueprint $table) {
            $table->string('prodcode', 20)->nullable()->comment('Бүтээгдэхүүнийн код');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_txn_journal', function (Blueprint $table) {
            $table->dropColumn('prodcode');
        });
    }
};
