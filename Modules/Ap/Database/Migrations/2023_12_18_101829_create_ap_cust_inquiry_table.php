<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн кредит дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust_inquiry', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('productno', 30)->comment('Бүтээгдэхүүний дугаар');
            $table->string('regno', 10)->comment('Регистр');
            $table->string('custtypeid', 2)->comment('Харилцагчийн төрөл');
            $table->string('pdf_url', 300)->nullable()->comment('Лавлагааны URL');
            $table->string('servicecode', 100)->nullable()->comment('Лавлагааны код');
            $table->dateTime('service_detail_date')->nullable()->comment('Лавлагаа авсан огноо');
            $table->bigInteger('price')->comment('Лавлагааны үнэ');
            $table->text('inquiry')->nullable()->comment('Лавлагаа дэлгэрэнгүй JSON');
            $table->string('purptypeid', 10)->comment('Лавлагааны зорилго ID');
            $table->string('purposedesc', 300)->comment('Лавлагааны зорилго тайлбар');
      
            $table->unsignedBigInteger('userid')->comment('Хэрэглэгчийн дугаар');
            //end status todorhoiloh
            $table->smallInteger('statusid')->default(1)->comment('Төлөв - 0 - Лавлагаа аваагүй, 1 - Лавлагаа амжилттай авсан, 2 - Лавлагаа амжилтгүй');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['servicecode']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_cust_inquiry');
    }
};
