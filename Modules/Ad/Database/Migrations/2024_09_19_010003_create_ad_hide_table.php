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
        Schema::create('ad_hide', function (Blueprint $table) {
            $table->id();
            $table->string('modulekey', 20)->comment('Системийн модулын түлхүүр, Жишээ нь: Custno, Acntno');
            $table->string('module', 3)->comment('Системийн модулын товьчлол, CR, DP, LN, IA, CT');
            $table->string('valuetype', 3)->comment('Утгын төрөл, U - хэрэглэгчийн id, B - салбарын дугаар, R - хэрэглэгчийн бүлэг ID,
            BU - хэрэглэгчийн id + салбарын дугаар, BR - салбарын дугаар + хэрэглэгчийн бүлгийн id, UR - хэрэглэгчийн id + хэрэглэгчийн бүлгийн id');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн id');
            $table->string('brchno', 4)->nullable()->comment('Салбарын дугаар');
            $table->unsignedBigInteger('roleid')->nullable()->comment('Хэрэглэгчийн бүлгийн id');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв, 1-идвэхтэй, -1...-идэвхгүй');
            $table->bigInteger('instid')->comment('Байгууллагын id');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгчийн id');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгчийн id');
            $table->timestamps();

            $table->unique(['instid', 'statusid', 'modulekey', 'module', 'valuetype', 'userid', 'brchno', 'roleid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_hide');
    }
};
