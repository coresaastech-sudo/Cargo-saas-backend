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
        Schema::create('cr_cust_sale_asset', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid')->comment('Харилцагчын ID');
            $table->smallInteger('custtypecode')->default(1)->comment('Харилцагчийн төрлийн код, 0 - хувь хүн 1 - байгууллага');
            $table->string('acntno', 20)->comment('Хүлээн авах харилцах Дансны дугаар');
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('cr_cust_sale_asset');
    }
};
