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
        Schema::table(
            'GP_inst_fee_rate',
            function (Blueprint $table) {
            $table->smallInteger('uselncount')->default(0)->comment('Зээлийн тоог ашиглах эсэх? 0-үгүй, 1-тийм');
            $table->smallInteger('loancount')->default(0)->comment('Зээлийн тоо');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('GP_inst_user');
    }
};
