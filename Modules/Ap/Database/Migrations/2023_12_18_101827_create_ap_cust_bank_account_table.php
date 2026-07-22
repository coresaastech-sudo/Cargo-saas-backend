<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н хэрэглэгчийн банкны дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust_bank_account', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('cust_user_id')->comment('Хэрэглэгчийн дугаар');
            $table->string('acnt_code', 20)->comment('Банкны дансны дугаар');
            $table->string('acnt_name', 120)->comment('Банкны дансны нэр');
            $table->string('bank_code', 10)->comment('Банкны код');
            $table->string('token', 64)->comment('Токен');
            $table->date('confirmed_at')->comment('Баталгаажуулсан огноо');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['cust_user_id', 'acnt_code', 'bank_code', 'statusid']);
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_cust_bank_account');
    }
};
