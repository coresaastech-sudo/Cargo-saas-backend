<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Гүйлгээнд шаардагдах дансны бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_qual', function (Blueprint $table) {

            $table->id();
            $table->string('txncode', 10)->comment('Гүйлгээний код');
            $table->string('prodcode', 10)->comment('Хамаарах бүтээгдэхүүний код');
            $table->string('acnttype1', 3)->default('00')->comment('Гүйлгээнд шаардагдах дансны төрөл 00 - сонгогдсон данс IA - Дотоодын данс GL - Ерөнхий дэвтрийн данс SP - Тодорхой бус дансны код');
            $table->string('acntno1', 20)->nullable()->comment('Гүйлгээнд оролцох дансны дугаар');
            $table->string('acnttype2', 3)->default('00')->comment('Гүйлгээнд шаардагдах дансны төрөл 00 - сонгогдсон данс IA - Дотоодын данс GL - Ерөнхий дэвтрийн данс SP - Тодорхой бус дансны код');
            $table->string('acntno2', 20)->nullable()->comment('Гүйлгээнд оролцох дансны дугаар');
            $table->smallInteger('clscode')->default(0)->comment('Ангилалын код');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'txncode', 'prodcode', 'clscode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_qual');
    }
};
