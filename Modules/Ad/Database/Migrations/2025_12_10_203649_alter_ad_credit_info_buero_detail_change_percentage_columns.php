<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * interestinperc, loaninterest, commissionperc талбаруудыг smallInteger-оос decimal болгох
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CREDIT_BUERO_DETAIL");

        Schema::table('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->decimal('interestinperc', 5, 2)->comment('Зээлийн төлөгдөх хүүгийн хувь')->change();
            $table->decimal('loaninterest', 5, 2)->nullable()->comment('Зээлийн хүүгийн хувь')->change();
            $table->decimal('commissionperc', 5, 2)->comment('Зээлийн төлөгдөх хураамжийн хувь')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->smallInteger('interestinperc')->comment('Зээлийн төлөгдөх хүүгийн хувь')->change();
            $table->smallInteger('loaninterest')->nullable()->comment('Зээлийн хүүгийн хувь')->change();
            $table->smallInteger('commissionperc')->comment('Зээлийн төлөгдөх хураамжийн хувь')->change();
        });
    }
};
