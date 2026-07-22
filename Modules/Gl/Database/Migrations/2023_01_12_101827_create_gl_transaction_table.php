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
        Schema::create('gl_transaction', function (Blueprint $table) {


            $table->string('journal', 10)->comment('Журналын дугаар');
            $table->integer('entry')->default(0)->comment('Нэгж дугаар');
            $table->integer('year')->comment('Жил');
            $table->integer('period')->comment('Сар');
            $table->integer('day')->comment('Өдөр');
            $table->string('branch', 6)->comment('Салбарын дугаар');
            $table->string('unit', 4)->default('0000')->comment('Нэгж дугаар');
            $table->string('currency', 3)->comment('Валютын код');
            $table->string('account', 16)->comment('Дансны дугаар');
            $table->decimal('amount', 23, 8)->default(0)->comment('Эхлэл баланс');
            $table->string('description', 100)->nullable()->comment('Дансны дугаар');
            $table->smallInteger('correctoin')->default(0)->comment('Дебит дүн');
            $table->smallInteger('statusid')->default(1)->comment('Кредит дүн');
            $table->date('postdate')->comment('Гүйлгээ хийгдсэн огноо');
            $table->integer('tellerno')->comment('Теллерийн дугаар');
            $table->date('txndate', 23, 8)->comment('Гүйлгээний огноо');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->primary([ 'instid', 'journal', 'entry']);
            $table->index([ 'instid', 'year', 'period', 'day']);
            $table->index([ 'instid', 'branch', 'unit', 'account']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_transaction');
    }
};
