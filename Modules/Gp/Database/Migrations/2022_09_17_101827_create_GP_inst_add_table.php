<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Байгууллагын нэмэлт мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_add', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instid')->comment('Байгууллагын код');
            $table->bigInteger('keyfield')->comment('Нэмэлт мэдээллийн дугаар');
            $table->string('itemvalue', 2000)->nullable()->comment('Нэмэлт мэдээллийн утга');
            $table->smallInteger('statusid')->comment('Төлөв -1 - устгасан, 1 - идвэхтэй');
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
        Schema::dropIfExists('GP_inst_add');
    }
};
