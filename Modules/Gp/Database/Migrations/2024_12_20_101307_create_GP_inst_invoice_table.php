<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_invoice', function (Blueprint $table) {
            $table->id();
            $table->string('invoiceno', 20)->comment('Нэхэмжлэхийн дугаар');
            $table->date('startdate')->comment('Билл эхлэх өдөр');
            $table->date('enddate')->comment('Билл дуусах өдөр');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-үүссэн, -1-устгасан, 2-дутуу төлөлттэй, 3-хугацаа хэтэрсэн, 4-төлөгдсөн');
            $table->decimal('base_amount', 19, 2)->default(0)->comment('Тарифийн Дүн');
            $table->decimal('inflation_rate', 4, 2)->default(0)->comment('Инфляци хувь');
            $table->decimal('discount_amount', 19, 2)->default(0)->comment('Нэхэмжлэхийн хөнгөлөлтийн дүн');
            $table->decimal('tax_amount', 19, 2)->default(0)->comment('НӨАТ дүн');
            $table->decimal('invoice_amount', 19, 2)->default(0)->comment('Нэхэмжлэх Дүн');
            $table->date('expirydate')->nullable()->comment('Нэхэмжлэхийн төлөлт хийх өдөр');
            $table->smallInteger('freq')->comment('Нэхэмжлэх үүссэх давтамж, 1-Сар бүр, 2-Улирал тутам');
            $table->smallInteger('cutoffday')->nullable()->comment('Нэхэмжлэл үүсэх өдөр');
            $table->smallInteger('gracepriod')->nullable()->comment('Төлөлт хүлээх хоног');
            $table->string('description', 500)->nullable()->comment('Тайлбар');
            $table->smallInteger('is_sendmail')->default(0)->comment('Мэйл илгээсэн эсэх, 1-тийм, 0-үгүй');
            $table->decimal('paid_amount', 19, 2)->default(0)->comment('Төлсөн дүн');
            $table->date('paiddate')->nullable()->comment('Төлбөр хийсэн огноо');
            $table->string('taxid', 50)->nullable()->comment('Татварын дугаар');
            $table->string('bankaccountno', 20)->comment('Фиба банк Дансны дугаар');
            $table->string('error', 500)->nullable()->comment('Алдааны мэдээлэл');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
            $table->unique(['invoiceno', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_invoice');
    }
};
