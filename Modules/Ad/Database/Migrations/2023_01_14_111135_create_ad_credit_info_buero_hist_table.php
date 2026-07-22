<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ЗМС мэдээ нийлүүлэлтийн түүх
     * @return void
     */
    public function up()
    {
        Schema::create('ad_credit_info_buero_hist', function (Blueprint $table) {
            $table->id();
            $table->dateTime('lastexecuteddate')->comment('Ажилласан огноо');
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
