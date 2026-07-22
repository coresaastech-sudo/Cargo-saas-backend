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
            $table->string('prodcode', 10)->nullable()->comment('Гүйлгээний тайлбарын харгалзах бүтээгдэхүүний код')->change();
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
            $table->string('prodcode', 10)->comment('Гүйлгээний тайлбарын харгалзах бүтээгдэхүүний код');
        });
    }
};
