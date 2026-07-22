<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Харилцагчийн орлогын мэдээллийг хадгалах хүснэгт
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('ap_cust_income');
        Schema::create('ap_cust_income', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('regno')->comment('Харилцагчийн регистрийн дугаар');
            $table->string('cif')->comment('Харилцагчийн дугаар');
            $table->unsignedBigInteger('cust_userid')->comment('Харилцагчийн дугаар App');
            $table->string('type', 20)->comment('Орлогын төрөл: salary - Цалин, sales - Борлуулалтын орлого');
            $table->string('source_name')->comment('Эх үүсвэрийн нэр')->nullable();
            $table->string('year')->comment('Жил');
            $table->smallInteger('month')->comment('Сар');
            $table->decimal('amount', 23, 8)->default(0)->comment('Орлогийн хэмжээ');
            $table->decimal('fee', 23, 8)->default(0)->comment('Зардал, Татвар')->nullable();
            $table->decimal('net_income', 23, 8)->default(0)->comment('Цэвэр орлого');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('ap_cust_income');
    }
};
