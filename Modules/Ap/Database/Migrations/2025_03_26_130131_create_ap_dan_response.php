<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ap_dan_response', function (Blueprint $table) {
            $table->id()->comment('ID дугаар');
            $table->string('code', 200)->nullable()->comment('Код');
            $table->string('access_token', 200)->nullable()->comment('Нэвтрэх токен');
            $table->smallInteger('statusid')->nullable()->default(1)->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй');
            $table->string('description', 100)->nullable()->comment('Тайлбар');
            $table->smallInteger('isused')->nullable();
            $table->unsignedBigInteger('userid')->nullable();
            $table->text('services')->nullable()->comment('Үйлчилгээ');;
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
        Schema::dropIfExists('ap_dan_response');
    }
};
