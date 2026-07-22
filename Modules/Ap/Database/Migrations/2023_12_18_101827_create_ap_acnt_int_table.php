<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн хүүний мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_int', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');

            $table->string('pay_cust_name', 100)->nullable()->comment('Харилцагчийн нэр');
            $table->decimal('int_rate', 23, 5)->nullable()->comment('Хүү');
            $table->string('source_bal_type', 50)->nullable()->comment('Үлдэгдэлийн төрөл');
            $table->string('bal_type_code', 50)->nullable()->comment('Үлдэгдлийн төрлийн код');
            $table->text('last_acr_info')->nullable()->comment('Сүүлд хуримтлуулсан мэдээлэл');
            $table->string('type', 50)->nullable()->comment('Төрөл');
            $table->decimal('daily_int_amt', 23, 5)->nullable()->comment('Өдрийн хүүний дүн');
            $table->decimal('accr_int_amt', 23, 5)->nullable()->comment('Хүүний дүн');
            $table->string('int_type_code', 50)->nullable()->comment('Хүүний төрлийн код');
            $table->string('int_type_name', 100)->nullable()->comment('Хүүний төрөл нэр');
            $table->string('int_rate_option', 50)->nullable()->comment('Хүүний хувь төрөл');
            $table->decimal('last_acr_amt', 23, 5)->nullable()->comment('Сүүлийн хуримтлуулсан дүн');
            $table->date('last_accrual_date')->nullable()->comment('Сүүлийн хуримтлуулсан огноо');
            $table->smallInteger('last_acr_txn_seq')->nullable()->comment('Сүүлийн хуримтлуулсан гүйлгээний дугаар');
            $table->string('int_lvl', 1)->nullable()->comment('Хүүний түвшин');
            $table->string('int_lvl_name', 50)->nullable()->comment('Хүүний түвшин нэр');
            $table->string('other_info', 500)->nullable()->comment('Бусад мэдээлэл');


            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
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
        Schema::dropIfExists('ap_acnt_int');
    }
};
