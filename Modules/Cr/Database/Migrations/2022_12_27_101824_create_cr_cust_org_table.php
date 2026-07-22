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
        Schema::create('cr_cust_org', function (Blueprint $table) {
            $table->id();
            $table->string('custno', 20)->comment('Харилцагчийн дугаар');
            $table->string('image', 100)->comment('Зураг');
            $table->string('id1', 20)->comment('Улсын бүртгэлийн дугаар 1');
            $table->string('id1typecode', 20)->comment('Улсын бүртгэлийн дугаар 1-н маск');
            $table->string('id2typecode', 20)->nullable()->comment('Улсын бүртгэлийн дугаар 2-н маск');
            $table->string('id2', 20)->nullable()->comment('Улсын бүртгэлийн дугаар 2');
            $table->string('name', 100)->comment('Нэр');
            $table->string('name2', 100)->comment('Нэр2');
            $table->string('segcode', 6)->nullable()->comment('Сегмэнт код');
            $table->string('dirname', 50)->nullable()->comment('Захирлын нэр');
            $table->string('dirname2', 50)->nullable()->comment('Захирлын нэр2');
            $table->string('dirlname', 50)->nullable()->comment('Захирлын овог');
            $table->string('dirlname2', 50)->nullable()->comment('Захирлын овог2');
            $table->string('diridcode', 20)->nullable()->comment('Захирлын улсын бүртгэлийн дугаар маск');
            $table->string('dirid', 20)->nullable()->comment('Захирлын улсын бүртгэлийн дугаар');
            $table->string('contactpname', 50)->nullable()->comment('Холбоо барих ажилтаны нэр');
            $table->string('contactppos', 100)->nullable()->comment('Холбоо барих ажилтаны албан тушаал');
            $table->string('contactpphone', 40)->nullable()->comment('Холбоо барих ажилтаны утас');
            $table->string('orgtypecode', 2)->comment('Байгууллагын төрлийн код');
            $table->smallInteger('custtypecode')->default(1)->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага');
            $table->smallInteger('bl')->default(0)->comment('Хар жагсаалтад бүртгэлтэй эсэх 1 - тийм, 0 - үгүй');
            $table->smallInteger('loancount')->default(0)->comment('Зээлийн авсан тоо');
            $table->string('inducode', 6)->nullable()->comment('Үйл ажиллагааны үндсэн чиглэл');
            $table->string('indusubcode', 6)->nullable()->comment('Үйл ажиллагааны дэд чиглэл');
            $table->string('countrycode', 3)->nullable()->comment('Улсын код');
            $table->string('workphone', 40)->nullable()->comment('Утас');
            $table->string('email', 60)->nullable()->comment('Имэйл');
            $table->string('catcode', 4)->nullable()->comment('Категор');
            $table->date('birthdate')->nullable()->comment('Байгууллагдсан огноо');
            $table->date('lastrenewdate')->nullable()->comment('Сүүлд мэдээлэл шинэчлэсэн огноо');
            $table->string('brchno', 6)->comment('Салбар');
            $table->string('created_name', 100)->nullable()->comment('Бүртгэсэн ажилтаны нэр');
            $table->string('updated_name', 100)->nullable()->comment('Өөрчлөсөн ажилтаны нэр');
            $table->smallInteger('empcount')->default(0)->comment('Ажилтны тоо');
            $table->smallInteger('statusid')->comment('Төлөв');
            $table->smallInteger('prevstatusid')->comment('Өмнөх төлөв');
            $table->date('lasttxndate')->nullable()->comment('Сүүлд гүйлгээ хийсэн огноо');
            $table->smallInteger('card')->nullable()->comment('Карттай эсэх');
            $table->char('ispolitical', 1)->nullable()->comment('Улс төрт нөлөөтэй эсэх');
            $table->string('hidden', 1)->nullable()->comment('Нууцлах эсэх');
            $table->integer('managerno')->nullable()->default(0)->comment('Хариуцсан харилцагчийн менежерийн дугаар');
            $table->string('manager_name', 100)->nullable()->comment('Хариуцсан харилцагчийн менежер нэр');
            $table->string('sourcecode', 4)->nullable()->comment('Бүртгэсэн суваг');
            $table->bigInteger('instid')->comment('Санхүүгийн байгууллагын дугаар');
            $table->date('txndate')->nullable()->comment('Customer registration transaction date');
            // $table->string('tin', 14)->nullable()->comment('Татвар төлөгчийн дугаар');
            $table->unsignedBigInteger('created_by')->comment('');
            $table->unsignedBigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('cr_cust_org');
    }
};
