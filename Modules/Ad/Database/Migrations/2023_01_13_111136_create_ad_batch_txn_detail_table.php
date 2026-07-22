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
        Schema::create('ad_batch_txn_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batchid')->comment('Багцын дугаар');
            $table->string('acntno', 20)->comment('Дансны дугаар');
            $table->date('txndate')->comment('Гүйлгээ хийсэн огноо');
            $table->string('txncode', 10)->nullable()->comment('Гүйлгээний код');
            $table->string('txndesc', 200)->nullable()->comment('Гүйлгээний утга');
            $table->text('description')->nullable()->comment('Дэлгэрэнгүй алдааны тайлбар');
            $table->bigInteger('jrno')->comment('Журналын дугаар')->nullable();
            $table->smallInteger('statusid')->default(0)->comment('1 - амжилттай, 0 - Алдаатай, 2-Processing');
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
