<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н Байгууллага өөрийн үйлчилгээг түр зогсоох мэдээлэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_inst_stop_service', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('name', 50)->comment('Нэр');
            $table->string('prod_code', 30)->nullable()->comment('Бүтээгдэхүүний дугаар');
            $table->string('prod_type', 30)->nullable()->comment('Бүтээгдэхүүний төрөл');
            $table->string('operation', 12)->nullable()->comment('Процесс код');
            $table->string('description', 250)->nullable()->comment('Тайлбар');
            $table->date('begin_date')->comment('Эхлэх огноо');
            $table->date('end_date')->comment('Дуусах огноо');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
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
        Schema::dropIfExists('ap_inst_stop_service');
    }
};
