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
        Schema::table('ad_cgw_txn_desc_combination', function (Blueprint $table) {
            $table->string('type', 2)->default(1)->nullable()->comment('Төрөл: 1 - Бүтээгдэхүүн, 2 - Дансаар');
            $table->string('acntno', 20)->nullable()->comment('Дансны дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_cgw_txn_desc_combination', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('acntno');
        });
    }
};
