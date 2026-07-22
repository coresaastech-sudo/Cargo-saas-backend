<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Харилцагчийн гарын үсгийг дансанд уясан холбоос
     * @return void
     */
    public function up()
    {
        Schema::create('cr_cust_sign_acnt', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid')->comment('Харилцагчийн id');
            $table->bigInteger('signid')->comment('Гарын үсгийн id');
            $table->smallInteger('sign_level')->comment('Гарын үсгийн дэс дугаар');
            $table->string('acnt_module', 3)->comment('Дансны модул');
            $table->string('acntno', 20)->comment('Дансны дугаар');
            $table->smallInteger('statusid')->comment('Төлөв 1-идэвхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->integer('created_by')->nullable()->comment('Бүртгэл хийсэн ажилтан');
            $table->integer('updated_by')->nullable()->comment('Өөрчлөлт хийсэн ажилтан');
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
        Schema::dropIfExists('cr_cust_sign_acnt');
    }
};
