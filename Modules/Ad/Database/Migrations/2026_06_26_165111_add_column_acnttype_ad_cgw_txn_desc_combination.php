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
            $table->string('acnttype', 3)->nullable()->comment('Дансны төрөл, ln-зээл, dp-депозит, ia-дотоод');
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
            $table->dropColumn(['acnttype']);
        });
    }
};
