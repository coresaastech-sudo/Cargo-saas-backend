<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Corporate gateway ашиглан гаргаж буй гүйлгээний жагсаалт
     * @return void
     */
    public function up()
    {
        Schema::create('ad_cgw_transaction', function (Blueprint $table) {
            $table->id();
            $table->string('jrno', 30)->nullable()->comment('Журналын дугаар');
            $table->string('from_account', 20)->nullable()->comment('Гүйлгээ хийх дансны дугаар');
            $table->decimal('amount', 23, 8)->comment('Гүйлгээний дүн');
            $table->string('curcode', 3)->comment('Валют');
            $table->string('description', 500)->nullable()->comment('Гүйлгээний утга');
            $table->string('to_bank', 10)->comment('Хүлээн авах банкны код');
            $table->string('to_account', 20)->comment('Хүлээн авах дансны дугаар');
            $table->string('to_account_name', 200)->nullable()->comment('Хүлээн авагчийн нэр');
            $table->string('transferid', 30)->nullable()->comment('Банкны гүйлгээний дугаар');
            $table->dateTime('system_date')->comment('Банкны гүйлгээ хийгдсэн огноо');
            $table->string('uuid', 40)->comment('Банкны гүйлгээний код');
            $table->smallInteger('source')->default(1)->nullable()->comment('1-Me App');
            $table->smallInteger('statusid')->comment('Төлөв -1 - устсан; 0 - Хүлээгдэж буй; 1 - Амжилттай;2 - Амжилтгүй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');

            $table->timestamps();
            $table->unique(['jrno', 'instid', 'to_bank']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_cgw_transaction');
    }
};
