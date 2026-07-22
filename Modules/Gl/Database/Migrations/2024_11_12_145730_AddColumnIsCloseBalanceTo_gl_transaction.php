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
        Schema::table('gl_transaction', function (Blueprint $table) {
            $table->smallInteger('isclosebalance')->default(0)->comment('Жилийн эцсийн орлого зардал хаах гүйлгээ бол 1');
           });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gl_transaction', function (Blueprint $table) {
            $table->dropColumn('isclosebalance');
        });
    }
};
