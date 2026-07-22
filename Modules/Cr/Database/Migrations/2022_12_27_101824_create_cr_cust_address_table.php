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
        Schema::create('cr_cust_address', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid');
            $table->smallInteger('custtypecode');
            $table->smallInteger('addrtypecode');
            $table->smallInteger('apprtypecode');
            $table->smallInteger('statusid');
            $table->bigInteger('state')->nullable();
            $table->bigInteger('region')->nullable();
            $table->string('subregion', 10)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->string('w3w', 50)->nullable();
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
        Schema::dropIfExists('cr_cust_address');
    }
};
