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
        Schema::create('cr_cust_ind', function (Blueprint $table) {
            $table->id();
            $table->string('custno', 20)->comment('Харилцагчийн дугаар');
            $table->string('image', 100)->comment('Зураг');
            $table->string('familyname', 100)->comment('Ургийн овог');
            $table->string('familyname2', 100)->comment('Ургийн овог 2');
            $table->string('lname', 100)->comment('Эцэг эхийн нэр');
            $table->string('lname2', 100)->comment('Эцэг эхийн нэр2');
            $table->string('name', 100)->comment('Нэр');
            $table->string('name2', 100)->comment('Нэр2');
            $table->smallInteger('sexcode')->default(1)->comment('Хүйс');
            $table->string('id1typecode', 20)->comment('Улсын бүртгэлийн дугаар 1-н маск');
            $table->string('id2typecode', 20)->nullable()->comment('Улсын бүртгэлийн дугаар 2-н маск');
            $table->string('id1', 20)->comment('Улсын бүртгэлийн дугаар 1');
            $table->string('id2', 20)->nullable()->comment('Улсын бүртгэлийн дугаар 2');
            $table->date('birthdate')->nullable()->comment('Төрсөн өдөр');
            $table->string('segcode', 6)->nullable()->comment('Сегмэнт код');
            $table->string('inducode', 6)->nullable()->comment('Үйл ажиллагааны үндсэн чиглэл');
            $table->string('indusubcode', 6)->nullable()->comment('Үйл ажиллагааны дэд чиглэл');
            $table->string('catcode', 4)->nullable()->comment('Категор');
            $table->string('handphone', 40)->nullable()->comment('Гар утас');
            $table->string('email', 60)->nullable()->comment('Имэйл');
            $table->smallInteger('titlecode')->default(0)->comment('Өргөмжлөл');
            $table->smallInteger('langcode')->default(0)->comment('Хэл');
            $table->smallInteger('nationcode')->default(0)->comment('Үндэстэн');
            $table->smallInteger('educode')->default(0)->comment('Боловсрол');
            $table->string('profession', 60)->nullable()->comment('Мэргэжил');
            $table->string('countrycode', 3)->nullable()->comment('Улс');
            $table->smallInteger('maritalstatuscode')->default(0)->comment('');
            $table->integer('familymembercount')->nullable()->comment('Гэр бүлийн гишүүдын тоо');
            $table->smallInteger('custtypecode')->default(0)->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага');
            $table->smallInteger('bl')->default(0)->comment('Хар жагсаалтад бүртгэлтэй эсэх 1 - тийм, 0 - үгүй');
            $table->smallInteger('loancount')->default(0)->comment('Зээлийн авсан тоо');
            $table->smallInteger('statusid')->comment('Төлөв');
            $table->string('sourcecode', 4)->nullable()->comment('Бүртгэсэн суваг');
            $table->bigInteger('instid')->comment('Санхүүгийн байгууллагын дугаар');
            $table->date('txndate')->nullable()->comment('Customer registration transaction date');
            $table->date('lasttxndate')->nullable()->comment('Сүүлд гүйлгээ хийсэн огноо');
            $table->string('brchno', 6)->comment('Салбар');
            $table->string('created_name', 100)->nullable()->comment('Бүртгэсэн ажилтаны нэр');
            $table->string('updated_name', 100)->nullable()->comment('Өөрчлөсөн ажилтаны нэр');
            $table->char('ispolitical', 1)->nullable()->comment('Улс нөлөө бүхий эсэх');
            $table->smallInteger('prevstatusid')->comment('Өмнөх төлөв');
            $table->string('workplace', 100)->nullable()->comment('Ажлын газрын нэр');
            $table->string('position', 75)->nullable()->comment('Албан тушаал');
            $table->smallInteger('card')->nullable()->comment('Карттай эсэх');
            $table->date('lastrenewdate')->nullable()->comment('Сүүлд мэдээлэл шинэчлэсэн огноо');
            $table->integer('managerno')->nullable()->default(0)->comment('Хариуцсан харилцагчийн менежерийн дугаар');
            $table->string('manager_name', 100)->nullable()->comment('Хариуцсан харилцагчийн менежер нэр');
            $table->string('hidden', 1)->nullable()->comment('Нууцлах эсэх');
            $table->unsignedBigInteger('created_by')->comment('');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('');
            $table->index(['custno', 'instid']);
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
        Schema::dropIfExists('cr_cust_ind');
    }
};
