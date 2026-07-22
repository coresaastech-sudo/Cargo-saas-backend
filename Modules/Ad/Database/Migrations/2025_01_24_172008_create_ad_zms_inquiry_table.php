<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ЗМС-аас авсан лавлагааны түүх
     * @return void
     */
    public function up()
    {
        Schema::create('ad_zms_inquiry', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('productno', 30)->nullable()->comment('Бүтээгдэхүүний код');
            $table->string('productname', 100)->nullable()->comment('Бүтээгдэхүүний нэр');
            $table->string('purptypeid', 30)->nullable()->comment('Лавлагааны зорилгын дугаар');
            $table->string('acnttypeid', 10)->nullable()->comment('Төлбөрийн нөхцөлийн ID');
            $table->string('custtypeid', 2)->nullable()->comment('Лавлуулагчийн төрөл');
            $table->string('custregno', 16)->nullable()->comment('Лавлагаа авах РД');
            $table->decimal('price', 23, 2)->default(0)->comment('Лавлагааны үнэ');
            $table->decimal('fee', 23, 2)->default(0)->comment('Mecore систем шимтгэл');
            $table->string('fee_acntno', 20)->nullable()->comment('Mecore систем шимтгэлийн данс');
            $table->smallInteger('origin')->default(1)->comment('Лавлагаа авсан систем, 1 - MECORE, 2 - MELP');
            $table->string('pdf', 500)->nullable()->comment('Лавлагааны pdf url');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв, 1 - Амжилттай, 0 - Амжилтгүй');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
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
        Schema::dropIfExists('ad_zms_inquiry');
    }
};
