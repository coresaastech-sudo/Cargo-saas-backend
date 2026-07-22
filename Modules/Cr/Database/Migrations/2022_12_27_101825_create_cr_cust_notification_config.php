<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *Харилцагч өөрөө мэдэгдэл авах эсхээ тохируулах
     * @return void
     */
    public function up()
    {
        Schema::create('cr_cust_notification_config', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custid')->comment('Харилцагчийн дугаар');
            $table->string('notiftype', 5)->comment('Мэдэгдэлийн төрөл SMS - SMS мэдэгдэл, PUSH - Push мэдэгдэл, MAIL - Мэйл мэдэгдэл ');
            $table->smallInteger('enabled')->comment('Мэдэгдэл тохируулсан эсэх 0 - Үгүй, 1 - Тийм');
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
        Schema::dropIfExists('cr_cust_notification_config');
    }
};
