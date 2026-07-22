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
        Schema::create('GP_db_backup_log', function (Blueprint $table) {
            $table->id();
            $table->string('path', 250)->comment('Нөөц авсан файлын зам');
            $table->integer('time')->default(0)->comment('Нөөц авсан хугацаа сек');
            $table->unsignedBigInteger('size')->nullable()->comment('Гарсан файл хэмжээ');
            $table->string('errordesc', 500)->nullable()->comment('Алдааны мэдээлэл');

            $table->smallInteger('statusid')->default(0)->comment('Төлөв 0 - Ажиллаж байгаа, -1 - устсан, 1 - Амжиллтай, 2 - Алдаатай');
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
        Schema::dropIfExists('GP_db_backup_log');
    }
};
