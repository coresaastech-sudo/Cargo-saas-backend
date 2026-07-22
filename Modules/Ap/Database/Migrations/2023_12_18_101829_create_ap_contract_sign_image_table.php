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
        Schema::create('ap_contract_sign_image', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->binary('image');
            $table->string('name');
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
        Schema::dropIfExists('ap_contract_sign_image');
    }
};
