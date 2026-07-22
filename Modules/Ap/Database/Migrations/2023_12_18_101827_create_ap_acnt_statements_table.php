<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн гүйлгээний хуулгын мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_statements', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');
            $table->string('jrno', 30)->nullable()->comment('Журналын дугаар');
            $table->date('txn_date')->nullable()->comment('Гүйлгээний огноо');
            $table->string('txn_code', 20)->nullable()->comment('Гүйлгээний код');
            $table->decimal('begin_bal', 23, 5)->nullable()->comment('Эхлэл баланс');
            $table->decimal('income', 23, 5)->nullable()->comment('Орлогын дүн');
            $table->decimal('outcome', 23, 5)->nullable()->comment('Зарлагын дүн');
            $table->decimal('end_bal', 23, 5)->nullable()->comment('Төгсгөлийн баланс');
            $table->decimal('balance', 23, 5)->nullable()->comment('Үлдэгдэл');
            $table->string('bal_type_code', 20)->nullable()->comment('Балансын төрөл');
            $table->string('txn_desc', 400)->nullable()->comment('Утга');
            $table->decimal('cont_cur_rate', 19, 5)->nullable()->comment('Харьцсан дансны валют');
            $table->string('cont_acnt_code', 20)->nullable()->comment('Харьцсан данс');
            $table->string('cont_bank_acnt_code', 35)->nullable()->comment('Банкны данс');
            $table->string('cont_bank_acnt_name', 200)->nullable()->comment('Банкны дансны нэр');
            $table->string('cont_bank_code', 50)->nullable()->comment('Банкны код');
            $table->string('cont_bank_name', 200)->nullable()->comment('Банкны нэр');
            $table->date('post_date')->comment('Каледарын огноо');

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
        Schema::dropIfExists('ap_acnt_statements');
    }
};
