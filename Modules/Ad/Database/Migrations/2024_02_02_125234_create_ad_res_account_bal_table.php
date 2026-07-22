<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Эрсдлийн сангийн дэлгэрэнгүй бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ad_res_account_bal', function (Blueprint $table) {

            $table->id();
            $table->string('acntno', 20)->comment('Дансны дугаар');
            $table->string('acnttype', 3)->comment('Дансны төрөл, ln-зээл, dp-депозит, cm-кредит, ia-дотоод');
            $table->decimal('balance', 23, 8)->comment('Үлдэгдэл');
            $table->smallInteger('clscode')->comment('Зээлийн чанарын ангилал');
            $table->date('resdate')->comment('Эрсдлийн сан үүссэн өдөр');
            $table->decimal('resbal', 23, 8)->comment('Эрсдлийн сан үлдэгдэл');
            $table->string('rescur', 3)->comment('Эрсдлийн сан байгуулсан валют');
            $table->string('res_acntno', 20)->comment('Эрсдлийн сан данс');
            $table->string('res_acnttype', 3)->comment('Эрсдлийн сан дансны төрөл');
            $table->string('cont_acntno', 20)->comment('Эрсдлийн сан харьцах данс');
            $table->string('cont_acnttype', 3)->comment('Эрсдлийн сан харьцах дансны төрөл');
            $table->decimal('amount', 23, 8)->comment('Гүйлгээний дүн');
            $table->smallInteger('rescls')->nullable()->comment('Хуучин ангилал');
            $table->string('errordesc', 100)->nullable()->comment('Алдааны мэдээлэл');

            $table->smallInteger('statusid')->comment('Төлөв 0 - хүлээгдэж байгаа, 1 - амжилттай, 3 - алдаатай, -1 - устсан');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['acntno', 'instid', 'statusid', 'resdate', 'rescls', 'clscode', 'amount'], 'ad_res_account_bal_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_res_account_bal');
    }
};
