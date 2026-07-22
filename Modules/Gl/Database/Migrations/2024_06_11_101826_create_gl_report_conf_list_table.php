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
        Schema::create('gl_report_conf_list', function (Blueprint $table) {

            $table->id()->comment('Дугаар');
            $table->string('name', 100)->comment('Тайлангын нэр');
            $table->string('name2', 100)->nullable()->comment('Тайлангын нэр хоёрдогч хэлээр');
            $table->smallInteger('colcount')->default(0)->comment('Баганын тоо');
            $table->string('AC', 8)->comment('Процесс код');
            $table->smallInteger('listorder')->default(0)->comment('Дэс дугаар');
            $table->smallInteger('statusid')->comment('Төлөв 1 - идэвхтэй, 0 - хаагдсан');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
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
        Schema::dropIfExists('gl_report_conf_list');
    }
};
