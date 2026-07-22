<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('corrid')->nullable();
            $table->string('cif', 20)->comment('Харилцагчийн дугаар');
            $table->string('familyname', 50)->nullable()->comment('Ургийн овог');
            $table->string('familyname2', 50)->nullable()->comment('Ургийн овог2');
            $table->string('lname', 50)->nullable()->comment('Овог');
            $table->string('lname2', 50)->nullable()->comment('Овог2');
            $table->string('fname', 50)->comment('Нэр');
            $table->string('fname2', 50)->nullable()->comment('Нэр2');
            $table->string('gender', 10)->nullable()->comment('Хүйс');
            $table->string('regno', 10)->comment('Регистр');
            $table->string('nationality', 20)->nullable()->comment('Иргэншил');
            $table->date('birthday')->nullable()->comment('Төрсөн өдөр');
            $table->string('lang', 20)->nullable()->comment('Хэл');
            $table->string('ethnicity', 20)->nullable()->comment('Яг үндэс');
            $table->string('citizenship', 20)->nullable()->comment('Иргэний харяалал');
            $table->string('birthplace', 50)->nullable()->comment('Төрсөн газар');
            $table->string('segment', 100)->nullable()->comment('Сегмент');
            $table->string('employment', 100)->nullable()->comment('Ажил эрхлэлт');
            $table->string('categories', 50)->nullable()->comment('Категори');
            $table->string('education', 100)->nullable()->comment('Боловсрол');
            $table->string('maritalstatus', 20)->nullable()->comment('Гэрлэлтийн байдал');
            $table->string('phone', 20)->nullable()->comment('Утас');
            $table->string('phone2', 20)->nullable()->comment('Утас2');
            $table->string('email', 50)->nullable()->comment('Имэйл');
            $table->string('fax', 15)->nullable()->comment('Факс');
            $table->smallInteger('familysize')->nullable()->comment('Гэр бүлийн гишүүн тоо');
            $table->string('region', 50)->nullable()->comment('Аймаг хот');
            $table->string('subregion', 50)->nullable()->comment('Сум дүүрэг');
            $table->string('address', 100)->nullable()->comment('Хаяг');
            $table->string('industry', 100)->nullable()->comment('Үйл ажиллагааны чиглэл');
            $table->string('shortname', 100)->nullable()->comment('Богино нэр');
            $table->string('shortname2', 50)->nullable()->comment('Богино нэр2');
            $table->smallInteger('isbl')->nullable()->comment('');
            $table->smallInteger('iscompanycustomer')->nullable()->comment('Байгууллагын ажилтан эсэх');
            $table->smallInteger('ispolitical')->nullable()->comment('Улс төрд нөлөө бүхий этгээд эсэх');
            $table->smallInteger('isvatpayer')->nullable()->comment('Татвар төлөгч эсэх');
            $table->unsignedBigInteger('monthlyincome')->nullable()->comment('Сарын орлого');
            $table->string('immovabletype', 10)->nullable()->comment('');
            $table->string('ownership', 5)->nullable()->comment('');
            $table->string('register_mask_code', 30)->nullable()->comment('Регистрийн маск');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['instid', 'cif', 'statusid']);
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_cust');
    }
};
