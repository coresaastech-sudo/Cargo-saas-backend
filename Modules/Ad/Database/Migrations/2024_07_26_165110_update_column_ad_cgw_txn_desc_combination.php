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
            $table->string('name', 50)->nullable()->comment('Гүйлгээний тайлбарын нэр')->change();
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
            $table->string('name', 50)->comment('Гүйлгээний тайлбарын нэр');
        });
    }
};
