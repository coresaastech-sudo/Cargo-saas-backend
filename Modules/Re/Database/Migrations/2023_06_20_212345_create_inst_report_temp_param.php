<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын загварын параметр
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp_param', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('templateid')->comment('Template ID');
            $table->bigInteger('parentid')->comment('Parent report parameter')->nullable();

            $table->string('paramname')->comment('Парам дуудхад ашиглах утга');

            $table->smallInteger('type')->comment('Төлөв - 1-Текст, 2-Number, 3-Boolean, 4-Date, 5-Array, 6-Collection, 7-List, 8-Sum, 9-Average, 10-Length of list');
            $table->smallInteger('isnull')->comment('Хоосон байж болох эсэх - 0-хоосон байж болохгүй, 1-хоосон байж болно');

            $table->smallInteger('evaluate')->comment('Бодолт хийх эсэх - 0 - хийхгүй 1 - хийнэ');
            $table->string('expression')->comment('Бодолт')->nullable();

            $table->string('header')->comment('Хүснэгтийн толгойд харагдах утга')->nullable();

            $table->unsignedBigInteger('tableid')->comment('Table id')->nullable();
            $table->unsignedBigInteger('fieldid')->comment('Field id')->nullable();

            $table->smallInteger('hasquery')->comment('Custom query байгаа эсэх')->nullable();
            $table->text('query')->comment('Custom query')->nullable();

            $table->smallInteger('hascondition')->comment('Custom query байхгүй үед where нөхцөл тавих хэрэгтэй бол. 0-үгүй, 1- тийм')->default(0);
            $table->string('condition')->comment('Custom condition')->nullable();

            $table->smallInteger('hasinput')->comment('query болон condition харгалзах оролттой эсэх - 0 - байхгүй, 1 - байгаа');

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
        Schema::dropIfExists('re_inst_report_temp_param');
    }
};
