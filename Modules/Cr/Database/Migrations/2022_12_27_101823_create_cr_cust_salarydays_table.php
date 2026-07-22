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
        Schema::create('cr_cust_salarydays', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid');
            $table->smallInteger('custtypecode');
            $table->smallInteger('salaryday');
            $table->string('name', 50);
            $table->string('name2', 50)->nullable();
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
        Schema::dropIfExists('cr_cust_salarydays');
    }
};
