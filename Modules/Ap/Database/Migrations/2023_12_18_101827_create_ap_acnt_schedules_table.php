<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн зээлийн дансны эргэн төлөлтийн хуваарийн мэдээлэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_schedules', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');

            $table->date('schd_date')->nullable()->comment('Хуваарийн огноо');
            $table->decimal('amount', 23, 5)->nullable()->comment('Төлөх зээлийн дүн');
            $table->decimal('int_amount', 23, 5)->nullable()->comment('Төлөх хүүгийн дүн');
            $table->decimal('total_amount', 23, 5)->nullable()->comment('Төлөх нийт дүн');
            $table->decimal('theor_bal', 23, 5)->nullable()->comment('Онолын үлдэгдэл');

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
        Schema::dropIfExists('ap_acnt_schedules');
    }
};
