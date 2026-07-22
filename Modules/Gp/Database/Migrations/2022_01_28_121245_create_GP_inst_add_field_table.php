<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Дансны нэмэлт мэдээллийн параметр
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_add_field', function (Blueprint $table) {

            $table->id();
            $table->string('typecode', 10)->comment('Модулийн төрөл');
            $table->string('name', 100)->comment('Талбарын нэр');
            $table->string('name2', 100)->nullable()->comment('Талбарын нэр2');
            $table->smallInteger('tagtype')->default(1)->comment('Талбарын төрөл. 1 - Тоо, 2 - Үсэг, 3 - Огноо, 4 - Decimal, 8 - Picture, 9 - Attach File');
            $table->smallInteger('taglen')->default(0)->comment('Талбарын урт. Энэ уртаар хязгаарлагдана');
            $table->string('tagmask', 30)->nullable()->comment('Загвар буюу маск-лаж оруулах бол үүнийг ашиглана');
            $table->smallInteger('mandatory')->default(0)->comment('Заавал оруулах эсэх. 1 - Заавал  0 - Заавал биш');
            $table->string('descr', 200)->nullable()->comment('Тайлбар');
            $table->integer('listorder')->default(0)->comment('Жагсаалтын эрэмбэ');
            $table->string('defaultvalue', 200)->nullable()->comment('Дефаулт утга');
            $table->smallInteger('readonly')->default(0)->comment('Зөвхөн унших эсэх');
            $table->decimal('minvalue', 23, 8)->default(0)->comment('Доод утга');
            $table->decimal('maxvalue', 23, 8)->default(9999999999)->comment('Дээд утга');

            $table->smallInteger('statusid')->comment('Төлөв  -1-устсан, 1-идвэхтэй');
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
        Schema::dropIfExists('GP_inst_add_field');
    }
};
