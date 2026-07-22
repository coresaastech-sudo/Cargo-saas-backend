<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ЗМС-рүү бүртгэх мэдээллийн action талбарын бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ad_credit_info_buero_action', function (Blueprint $table) {

            $table->id();
            $table->bigInteger('buero_id')->nullable()->comment('Багц мэдээ ID');
            $table->string('type', 30)->comment('Төрөл');
            $table->string('action', 10)->comment('action - add, update, delete');
            $table->string('key', 50)->comment('Түлхүүр талбар');
            $table->string('parent_key', 50)->comment('Эцэг түлхүүр талбар');
            $table->string('regno', 10)->comment('Регистр');

            $table->smallInteger('statusid')->comment('Төлөв  1 - Шинэ, 2 - Амжилттай нийлүүлсэн, 3 - Амжилтгүй нийлүүлсэн, -1 - устсан,');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['buero_id', 'type', 'key', 'parent_key','instid', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_credit_info_buero_action');
    }
};
