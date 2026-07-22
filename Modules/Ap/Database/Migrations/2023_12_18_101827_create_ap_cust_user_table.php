<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н хэрэглэгчийн мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust_user', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('email', 60)->comment('ИМэйл');
            $table->string('phone', 60)->nullable()->comment('Утас');
            $table->string('password', 64)->comment('Нууц үг');
            $table->date('passdate')->nullable()->comment('Нууц үг огноо');
            $table->smallInteger('passwrong')->nullable()->comment('Буруу оруулсан тоо');
            $table->string('regno', 18)->comment('Регистр');
            $table->smallInteger('iprest')->nullable()->comment('IP хаягаас хандах эхэс');
            $table->string('mustchGPss', 1)->nullable()->comment('Нууц үг солих ёстой эсэх');
            $table->string('passtoken', 100)->nullable()->comment('Токен');
            $table->date('passtokendate')->nullable()->comment('Токен огноо');
            $table->smallInteger('passtokenstatus')->nullable()->default(0)->comment('Токен төлөв');
            $table->string('firstname', 50)->comment('Нэр');
            $table->string('lastname', 50)->nullable()->comment('Овог');
            $table->string('use_google_auth', 1)->nullable()->comment('Google 2FA хэрэглэх эсэх');
            $table->string('google_auth_key', 100)->nullable()->comment('Google түлхүүр');
            $table->date('password_changed_at')->nullable()->comment('Нууц үг сольсон огноо');
            $table->string('photo_url', 100)->nullable()->comment('Зураг');
            $table->string('address', 100)->nullable()->comment('Хаяг');
            $table->string('region', 10)->nullable()->comment('Аймаг хот');
            $table->string('subregion', 10)->nullable()->comment('Сум дүүрэг');
            $table->string('device_token', 350)->nullable()->comment('Төхөөрөмжийн токен');
            $table->smallInteger('app_id')->nullable()->comment('Апп дугаар');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['email', 'statusid', 'app_id']);
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_cust_user');
    }
};
