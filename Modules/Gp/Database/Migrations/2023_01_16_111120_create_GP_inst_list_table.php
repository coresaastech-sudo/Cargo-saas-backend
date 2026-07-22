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
        Schema::create('GP_inst_list', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('Нэр');
            $table->string('name2', 200)->nullable()->comment('Нэр2');
            $table->string('dir_name', 50)->nullable()->comment('Захиралын нэр');
            $table->string('dir_name2', 50)->nullable()->comment('Захиралын нэр2');
            $table->string('regno', 20)->nullable()->comment('Регистрын дугаар');
            $table->string('nationid', 10)->nullable()->comment('Харяалагдах Улс');
            $table->date('stabledate')->nullable()->comment('Үүсгэн байгуулагдсан огноо');
            $table->string('inst_typeid', 10)->nullable()->comment('Байгууллагын төрөл');
            $table->string('license_typeid', 10)->nullable()->comment('Лицезийн төрөл');
            $table->string('color', 100)->nullable()->comment('Өнгийн код');
            $table->string('logo', 100)->nullable()->comment('Лого');
            $table->string('phone', 20)->nullable()->comment('Утас');
            $table->string('email', 50)->nullable()->comment('Имайл');
            $table->string('cbegno', 20)->default(0)->comment('Харилцагчийн дугаарлалт эхлэх');
            $table->string('cendno', 20)->default(0)->comment('Харилцагчийн дугаарлалт дуусах');
            $table->string('cnextno', 20)->default(0)->comment('Харилцагчийн дугаарлалт дараагын');
            $table->string('acntbegno', 20)->default(0)->comment('Дансны дугаарлалт эхлэх');
            $table->string('acntendno', 20)->default(0)->comment('Дансны дугаарлалт дуусах');
            $table->string('acntnextno', 20)->default(0)->comment('Дансны дугаарлалт дараагын');
            $table->string('iaacntbegno', 20)->default(0)->comment('Дотоодын дансны дугаарлалт эхлэх');
            $table->string('iaacntendno', 20)->default(0)->comment('Дотоодын дансны дугаарлалт дуусах');
            $table->string('iaacntnextno', 20)->default(0)->comment('Дотоодын дансны дугаарлалт дараагын');
            $table->string('appbegno', 20)->default(0)->comment('Зээлийн өргөдөлийн дугаарлалт эхлэх');
            $table->string('appendno', 20)->default(0)->comment('Зээлийн өргөдөлийн дугаарлалт дуусах');
            $table->string('appnextno', 20)->default(0)->comment('Зээлийн өргөдөлийн дугаарлалт дараагын');
            $table->string('collbegno', 20)->default(0)->comment('Барьцаа хөрөнгийн дугаарлалт эхлэх');
            $table->string('collendno', 20)->default(0)->comment('Барьцаа хөрөнгийн дугаарлалт дуусах');
            $table->string('collnextno', 20)->default(0)->comment('Барьцаа хөрөнгийн дугаарлалт дараагын');
            $table->string('deductionbegno', 20)->nullable()->comment('Хорогдуулалтын дансны дугаарлалт эхлэх');
            $table->string('deductionendno', 20)->nullable()->comment('Хорогдуулалтын дансны дугаарлалт дуусах');
            $table->string('deductionnextno', 20)->nullable()->comment('Хорогдуулалтын дансны дугаарлалт дараагын');
            $table->smallInteger('listorder')->nullable()->comment('Эрэмбэ');
            $table->bigInteger('state')->nullable()->comment('Аймаг хот');
            $table->bigInteger('region')->nullable()->comment('Сум дүүрэг');
            $table->string('subregion', 10)->nullable()->comment('Хороо баг');
            $table->string('street', 200)->nullable()->comment('Гудамж хаяг');
            $table->string('zipcode', 10)->nullable()->comment('Зип');
            $table->string('w3w', 50)->nullable()->comment('3 үгт хаяг');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв 1 бол идвэхтэй, 0 бол идвэхгүй, -1 бол устгасан');
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
        Schema::dropIfExists('GP_inst_list');
    }
};
