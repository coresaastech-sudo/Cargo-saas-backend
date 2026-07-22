<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Байгууллагын автомат ажилбарын бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ad_sent_notification', function (Blueprint $table) {
            $table->id();
            $table->string('reciever', 200)->comment('Илгээсэн мэйл хаяг');
            $table->string('title', 100)->comment('Илгээсэн гарчиг');
            $table->string('type', 20)->comment('Илгээсэн гарчиг');
            $table->string('description', 500)->nullable()->comment('Илгээсэн тайлбар');
            $table->text('body')->nullable()->comment('Илгээсэн мэйл');
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
        Schema::dropIfExists('ad_sent_notification');
    }
};
