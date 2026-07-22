<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->string('status', 2)->comment('Зээлийн дансны төлөв')->change();
            $table->string('loanintype', 3)->comment('Зээлийн төрөл')->change();
            $table->decimal('loan_additional_interest', 23, 8)->comment('Нэмэгдүүлсэн хүүгийн хувь')->change();
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
         });
    }
};
