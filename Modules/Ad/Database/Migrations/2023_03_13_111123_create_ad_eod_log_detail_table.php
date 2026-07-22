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
        Schema::create('ad_eod_log_detail', function (Blueprint $table) {
            $table->id();
            $table->date('eoddate')->comment('Өндөрлөлт хийсэн системийн огноо');
            $table->smallInteger('stepno')->comment('Алхамын дугаар');
            $table->string('acntno', 20)->comment('дансны дугаар')->nullable();
            $table->string('acntbrchno', 4)->comment('Дансны салбарын дугаар')->nullable();
            $table->string('errdesc', 2000)->comment('Алдааны тухай мэдээлэл')->nullable();
            $table->string('ACTION_CODE', 10)->comment('Процесс код')->nullable();
            $table->string('errtype', 1)->comment('A - хөнгөн асуудал D - хүндэвтэр асуудал F - ноцтой асуудал')->nullable();
            $table->smallInteger('orderno')->comment('Өдөр өндөрлөх процессийн алхамын дугаар');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'acntno', 'eoddate', 'stepno', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_eod_log_detail');
    }
};
