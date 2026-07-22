<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *  Corporate gateway ашиглан татаж буй гүйлгээний жагсаалт
     * @return void
     */
    public function up()
    {
        Schema::create('ad_corporate_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('bankcode', 6)->comment('Банкны код');
            $table->dateTime('banktxndate')->comment('Гүйлгээний огноо');
            $table->string('bankjrno', 25)->comment('Банкны гүйлгээний журнал дугаар');
            $table->string('bankacntno', 22)->comment('Банкны дансны дугаар');
            $table->decimal('txnamount', 23, 8)->comment('Гүйлгээний дүн');
            $table->string('sign', 1)->comment('Тэмдэг + орлого, - зарлага');
            $table->decimal('balance', 23, 8)->comment('Үлдэгдэл');
            $table->string('curcode', 3)->comment('Валют');
            $table->string('txndesc', 200)->nullable()->comment('Гүйлгээний утга');
            $table->string('acntno', 20)->nullable()->comment('Гүйлгээ хийгдсэн дансны дугаар');
            $table->string('txn_jrno', 20)->nullable()->comment('Журналын дугаар');
            $table->dateTime('txndate')->nullable()->comment('Гүйлгээний огноо');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - олдоогүй, 2 - төлөгдсөн');
            $table->smallInteger('source')->default(1)->nullable()->comment('1-систет 2-терминал 3-OPEN API');
            $table->string('reason', 50)->default('')->nullable()->comment('Гүйлгээний шалтгаан');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
            $table->unique(['banktxndate', 'bankjrno', 'instid', 'bankcode']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_corporate_gateway');
    }
};
