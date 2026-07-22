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
        Schema::create('gl_report_conf_detail', function (Blueprint $table) {

            $table->id()->comment('Дугаар');
            $table->unsignedBigInteger('report_conf_id')->comment('Тайлангын тохиргооны жагсаалтын ID');
            $table->string('num', 20)->nullable()->comment('Тайланд зурагдах дугаарлалт');
            $table->string('name', 100)->comment('Тайлангын үзүүлэлт нэр');
            $table->string('name2', 100)->nullable()->comment('Тайлангын үзүүлэлт нэр хоёрдогч хэлээр');
            $table->smallInteger('isbold')->default(0)->comment('Bold-р зурах эсэх, 0 - үгүй, 1 - тийм');
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
        Schema::dropIfExists('gl_report_conf_detail');
    }
};
