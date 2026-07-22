<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н Түгээмэл асуулт хариулт
     * @return void
     */
    public function up()
    {
        Schema::create('ap_faqs', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('question');
            $table->string('question2');
            $table->longText('answer')->nullable()->comment('Хариулт монгол хэл'); // HTML форматтай хариулт
            $table->longText('answer2')->nullable()->comment('Хариулт англи хэл');
            $table->smallInteger('listorder')->default(0)->comment('Эрэмбэ');
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('ap_faqs');
    }
};
