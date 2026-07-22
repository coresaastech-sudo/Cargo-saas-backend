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
        Schema::create('gl_account', function (Blueprint $table) {

            $table->string('acntno', 16)->comment('Дансны дугаар');
            $table->bigInteger('class')->nullable()->comment('Ерөнхий дэвтрийн дансны бүлэг');
            $table->string('name', 100)->comment('Дансны нэр');
            $table->string('name2', 100)->nullable()->comment('Дансны нэр хоёрдогч хэлээр');
            $table->string('type', 3)->nullable()->comment('Төрлийн код');
            $table->smallInteger('statusid')->comment('Төлөв 1 - идэвхтэй, 0 - хаагдсан');
            $table->smallInteger('listorder')->default(0)->comment('Дэс дугаар');
            $table->string('addinfo', 200)->nullable()->comment('Нэмэлт мэдээлэл');
            $table->string('addinfo2', 200)->nullable()->comment('Нэмэлт мэдээлэл');
            $table->string('catcode', 6)->nullable()->comment('Хэрэглэгч өөрийн хэрэгцээгээр ангилах');
            $table->string('centerbankaccount', 20)->nullable()->comment('Төв банкны дансны код');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->primary([ 'instid', 'acntno', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_account');
    }
};
