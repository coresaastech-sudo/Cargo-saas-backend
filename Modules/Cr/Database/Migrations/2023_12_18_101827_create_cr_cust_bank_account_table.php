<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Харилцагчийн банкны дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('cr_cust_bank_account', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('custid')->comment('Харилцагчийн ID');
            $table->string('custno', 20)->comment('Харилцагчийн дугаар');
            $table->string('acnt_code', 20)->comment('Банкны дансны дугаар');
            $table->string('iban', 20)->comment('Банкны iban дансны дугаар');
            $table->string('acnt_name', 120)->comment('Банкны дансны нэр');
            $table->string('bank_code', 10)->comment('Банкны код');
            $table->dateTime('confirmed_at')->nullable()->comment('Баталгаажуулсан огноо');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->bigInteger('instid')->comment('Санхүүгийн байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['instid', 'custno', 'acnt_code', 'bank_code', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cr_cust_bank_account');
    }
};
