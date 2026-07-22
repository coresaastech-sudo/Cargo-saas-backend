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
        Schema::create('ad_batch_registration_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('batchregistrationid');
            $table->bigInteger('rowid');
            $table->string('txncode', 10)->nullable()->comment('Гүйлгээний код');
            $table->text('requestdata')->nullable()->comment('Бичилтийн мөр дата');
            $table->text('description')->nullable()->comment('Дэлгэрэнгүй алдааны тайлбар');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв 0 - Ажиллаж байгаа, -1 - устсан, 1 - Амжиллтай, 2 - Алдаатай');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
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
        Schema::dropIfExists('ad_batch_registration_details');
    }
};
