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
        Schema::create('ad_auto_job', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('Ажилбарын нэр');
            $table->string('name2', 200)->nullable()->comment('Ажилбарын нэр 2');
            $table->smallInteger('formulaid')->nullable()->comment('Томъёоны ID');
            $table->string('ACTION_CODE', 12)->nullable()->comment('Процесс код');
            $table->string('execfreq', 1)->nullable()->comment('Ажиллах давтамж: M - сар бүр, Q - улирал бүр, B - хагас жилд 1 удаа, Y - жилд 1 удаа');
            $table->smallInteger('execday')->nullable()->comment('Ажиллах өдөр');
            $table->string('exectime', 20)->nullable()->comment('Ажиллах цаг');
            $table->string('job_type', 20)->nullable()->comment('Ажилбарын төрөл: P - ActionCode, S - schedule');
            $table->smallInteger('hastimelimit')->nullable()->comment('Хугацааны хязгаар байгаа эсэх 0 - Үгүй, 1 - Тийм');
            $table->date('startdate')->nullable()->comment('Эхлэх хугацаа');
            $table->date('enddate')->nullable()->comment('Дуусах хугацаа');
            $table->dateTime('lastexecdate')->nullable()->comment('Сүүлд ажилласан хугацаа');
            $table->smallInteger('formulaid')->nullable()->comment('Томъёоны ID');
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
        Schema::dropIfExists('ad_auto_job');
    }
};
