<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ЗМС мэдээ нийлүүлэлтийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ad_credit_info_buero', function (Blueprint $table) {
            $table->id();
            $table->string('custno', 20)->comment('Харилцагчын дугаар');
            $table->string('datapackageno', 10)->comment('XML файлын дугаар');
            $table->longText('request')->comment('ЗМС рүү илгээсэн хүсэлт');
            $table->longText('response')->comment('ЗМС ээс ирсэн хариу');
            $table->bigInteger('totalnum')->default(0)->comment('Илгээсэн зээлийн тоо');
            $table->bigInteger('successnum')->default(0)->comment('Амжилттай зээлийн тоо');
            $table->string('type', 5)->comment('Төрөл XML, JSON');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идэвхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_credit_info_buero');
    }
};
