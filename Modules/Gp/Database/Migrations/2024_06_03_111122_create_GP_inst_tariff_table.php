<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Байгууллагын үнэ тариф бүртгэл
     *
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_tariff', function (Blueprint $table) {
            $table->id();
            $table->string('depend', 2)->comment('Хамаарал');
            $table->unsignedBigInteger('interval')->comment('Интевалийн дээд дүн');
            $table->decimal('amount', 23, 2)->default(0)->comment('Дүн');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['depend','interval','statusid','instid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_tariff');
    }
};
