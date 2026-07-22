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
            $table->smallInteger('is_income')->nullable()->comment('1 - орлого, 0 - зарлага');
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
            $table->dropColumn(['is_income']);
        });
    }
};
