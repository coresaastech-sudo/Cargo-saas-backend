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
        Schema::table('ap_cust_bank_account', function (Blueprint $table) {
            $table->string('acnt_code', 50)->comment('Банкны дансны дугаар')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_cust_bank_account', function (Blueprint $table) {
            $table->string('acnt_code', 20)->comment('Банкны дансны дугаар')->change();
        });
    }
};
