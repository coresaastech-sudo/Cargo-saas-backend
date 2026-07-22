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
        Schema::create('cr_cust_relation', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid');
            $table->smallInteger('custtypecode');
            $table->bigInteger('custid2');
            $table->smallInteger('custid2typecode');
            $table->smallInteger('reltypecode');
            $table->smallInteger('relsubtypecode');
            $table->date('begindate')->nullable();
            $table->date('enddate')->nullable();
            $table->string('reldesc', 200)->nullable();
            $table->string('brchno', 6);
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
        Schema::dropIfExists('cr_cust_relation');
    }
};
