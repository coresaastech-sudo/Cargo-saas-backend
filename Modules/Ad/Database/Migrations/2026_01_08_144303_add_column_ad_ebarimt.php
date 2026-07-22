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
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            $table->string('acntno', 20)->nullable()->comment('Дансны дугаар');
            $table->decimal('refused_amount', 23, 8)->nullable()->comment('Илгээхээс хассан дүн');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_ebarimt', function (Blueprint $table) {
            $table->dropColumn('refused_amount');
            $table->dropColumn('acntno');
        });
    }
};
