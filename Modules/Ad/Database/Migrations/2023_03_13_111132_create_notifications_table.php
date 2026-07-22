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
        Schema::create('ad_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->comment('Мэдэгдэлийн гарчиг subject');
            $table->string('description', 500)->nullable()->comment('Тайлбар');
            $table->smallInteger('is_all')->default(0)->comment('Бүх хүнд мэдэгдэл илгээгдсэн эсэх 1-тийм, 0-үгүй');
            $table->string('notiftype', 5)->comment('Мэдэгдэлийн төрөл SMS - SMS мэдэгдэл, PUSH - Push мэдэгдэл, MAIL - Мэйл мэдэгдэл ');
            $table->smallInteger('execfreq')->comment('Давтамж 1 - яг одоо, 2 - Давтамжит');
            $table->smallInteger('usetemp')->comment('Тайлангийн загвар ашиглах эсэх 1-тийм, 0-үгүй');
            $table->string('reportActionCode', 20)->comment('Тайлангийн процесс дугаар');
            $table->smallInteger('autojobid')->nullable()->comment('автомат ажилбарын id');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв -1 - устсан, 1 - идэвхтэй, 0 - идэвхгүй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
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
        Schema::dropIfExists('ad_notifications');
    }
};
