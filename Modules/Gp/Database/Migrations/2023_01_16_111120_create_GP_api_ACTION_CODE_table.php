<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * API process code-н бүртгэл
     *
     * @return void
     */
    public function up()
    {
        Schema::create('GP_api_ACTION_CODE', function (Blueprint $table) {
            $table->string('api_ACTION_CODE', 10)->comment('Процесс код');
            $table->smallInteger('route')->default(0)->comment('Ямар замаар явах 0 - Controller function-г ашиглана. 1 - Дотоод процесс код ашиглана.');
            $table->string('name', 250)->comment('Нэр');
            $table->string('name2', 250)->nullable()->comment('Нэр 2');
            $table->string('controller', 250)->nullable()->comment('Контрол');
            $table->string('function', 250)->nullable()->comment('Функц');
            $table->string('ACTION_CODE', 8)->comment('Дотоод процесс код');
            $table->string('moduleid', 2)->nullable()->comment('Модулийн төрөл');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв 1 - идэвхтэй, -1 - устсан');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгч');
            $table->timestamps();

            $table->primary(['api_ACTION_CODE', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_api_ACTION_CODE');
    }
};
