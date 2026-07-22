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
        Schema::create('ad_perm_report', function (Blueprint $table) {
            $table->id();
            $table->string('AC', 8)->comment('Тайлангын процесс код');
            $table->string('valuetype', 3)->comment('Утгын төрөл, U - хэрэглэгчийн id, B - салбарын дугаар,');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн id');
            $table->string('brchno', 4)->nullable()->comment('Салбарын дугаар');
            $table->string('showbrchno', 4)->nullable()->comment('Харж чадах салбарын дугаар');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв, 1-идвэхтэй, -1...-идэвхгүй');
            $table->bigInteger('instid')->comment('Байгууллагын id');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгчийн id');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгчийн id');
            $table->timestamps();

            $table->unique(['instid', 'statusid', 'AC', 'valuetype', 'userid', 'brchno', 'showbrchno']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_perm_report');
    }
};
