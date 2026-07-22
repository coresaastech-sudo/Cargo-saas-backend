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
        Schema::create('GP_inst_txn_type', function (Blueprint $table) {
            $table->string('ACTION_CODE', 8)->comment('Гүйлгээний код');
            $table->string('name', 250)->comment('Нэр');
            $table->string('name2', 250)->nullable()->comment('Нэр 2');

            $table->smallInteger('txnopt')->default(0)->comment('0 - тохируулга хийгдэхгүй 1 - шимтгэлийн тохируулга хийгдэнэ 2 - 1 - р дансны тохируулга хийгдэнэ 3 - 2- р дансны тохируулга хийгдэнэ 4 - 1,2 дансны тохируулга 2-уулаа хийгдэнэ 5- шимтгэл болон 1- р дансны тохируулга хийгдэнэ 6 - шимтгэл болон 2-р дансны тохируулга хийгдэнэ 7 - шимтгэл болон 1,2 дансны тохируулга хийгдэнэ. 8 - user defined');
            $table->smallInteger('qualifier')->default(0)->comment('Данс нь бүтээгдэхүүнээс хамаарч өөр өөр байх эсэх. 1 - тийм 0- үгүй');
            $table->string('acnttype1', 4)->nullable()->comment('Гүйлгээнд шаардагдах дансны төрөл 00 - сонгогдсон данс IA - Дотоодын данс GL - Ерөнхий дэвтрийн данс SP - Тодорхой бус дансны код');
            $table->string('acntno1', 20)->nullable()->comment('Гүйлгээнд шаардагдах дансны дугаар ');
            $table->string('acnttype2', 4)->nullable()->comment('Гүйлгээнд шаардагдах дансны төрөл 00 - сонгогдсон данс IA - Дотоодын данс GL - Ерөнхий дэвтрийн данс SP - Тодорхой бус дансны код');
            $table->string('acntno2', 20)->nullable()->comment('Гүйлгээнд шаардагдах дансны дугаар');
            $table->string('moduleid', 2)->nullable()->comment('Модулийн төрөл');
            $table->smallInteger('txntype')->default(9)->comment('Гүйлгээний төрөл 9 - санхүүгийн бус гүйлгээ 5 - хоёрдмол 2 - санхүүгийн гүйлгээ');
            $table->string('rtypecode', 3)->default(1)->comment('Гүйлгээний дефаулт ханшийн төрөл');
            $table->smallInteger('isbatchfee')->nullable()->default(0)->comment('Багц шимтгэлтэй эсэх 0 - үгүй, 1 - тийм');
            $table->string('batchfeetxncode', 8)->nullable()->comment('Багц шимтгэлийн гүйлгээний код');
            $table->string('batchfeetxndesc', 75)->nullable()->comment('Багц шимтгэлийн гүйлгээний утга');

            $table->bigInteger('instid');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв 1 - идэвхтэй, -1 - устсан');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгч');
            $table->timestamps();

            $table->primary(['instid', 'ACTION_CODE', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_txn_type');
    }
};
