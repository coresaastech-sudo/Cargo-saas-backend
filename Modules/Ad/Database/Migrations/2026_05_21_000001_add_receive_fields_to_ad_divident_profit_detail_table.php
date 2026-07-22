<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ad_divident_profit_detail', function (Blueprint $table) {
            $table->string('recievemethod', 30)->nullable()->after('netamount');
            $table->string('recieve_acntno', 20)->nullable()->after('recievemethod');
            $table->string('bank_acntno', 50)->nullable()->after('recieve_acntno');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_divident_profit_detail', function (Blueprint $table) {
            $table->dropColumn(['recievemethod', 'recieve_acntno', 'bank_acntno']);
        });
    }
};
