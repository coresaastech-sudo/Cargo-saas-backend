<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн гэрээний мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust_contracts', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('cust_cif', 20)->comment('Харилцагчийн дугаар');
            $table->string('cust_name', 100)->comment('Нэр');
            $table->string('operation', 50)->comment('Процесс код');
            $table->string('account_no', 20)->comment('Дансны дугаар');
            $table->string('prod_code', 50)->comment('Бүтээгдэхүүний код');
            $table->string('txn_jrno', 50)->nullable()->comment('Гүйлгээний журналын дугаар');
            $table->text('contract')->nullable()->comment('Гэрээ');

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
        Schema::dropIfExists('ap_cust_contracts');
    }
};
