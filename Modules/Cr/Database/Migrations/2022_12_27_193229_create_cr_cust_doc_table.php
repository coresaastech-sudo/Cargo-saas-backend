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
        Schema::create('cr_cust_doc', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid');
            $table->smallInteger('custtypecode');
            $table->binary('file');
            $table->string('name');
            $table->string('name2')->nullable();
            $table->string('filename');
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
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
        Schema::dropIfExists('cr_cust_doc');
    }
};
