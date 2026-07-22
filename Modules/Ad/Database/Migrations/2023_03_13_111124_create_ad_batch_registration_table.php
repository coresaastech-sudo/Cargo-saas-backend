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
        Schema::create('ad_batch_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 250)->comment('файлын нэр');
            $table->text('requestdata')->nullable()->comment('Бичилтийн мөр дата');
            $table->integer('time')->default(0)->comment('Ажилласан хугацаа сек');
            $table->integer('count')->default(0)->comment('Нийт бичилтийн тоо');
            $table->integer('errorcount')->default(0)->comment('Нийт Алдаатай бичилтийн тоо');
            $table->integer('successcount')->default(0)->comment('Нийт Амжилттай бичилтийн тоо');
            $table->unsignedBigInteger('size')->nullable()->comment('Гарсан файл хэмжээ');
            $table->text('errordesc')->nullable()->comment('Алдааны мэдээлэл');
            $table->smallInteger('process')->default(0)->comment('0 - pending, 1 - finished, 2 - processing, 3 - stopped');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв 0 - Ажиллаж байгаа, -1 - устсан, 1 - Амжиллтай, 2 - Алдаатай');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
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
        Schema::dropIfExists('ad_batch_registrations');
    }
};
