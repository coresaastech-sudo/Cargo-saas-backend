<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Түр дансны бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_susp', function (Blueprint $table) {

            $table->id();
            $table->string('acntcode', 10)->comment('Тодорхой бус дансны код');
            $table->string('brchno', 4)->nullable()->comment('Салбарын дугаар');
            $table->string('curcode', 3)->nullable()->comment('Валютын код');
            $table->string('acnttype', 3)->comment('Дансны төрөл');
            $table->string('acntno', 20)->comment('Дансны дугаар');
            $table->string('acntdesc', 400)->nullable()->comment('Тайлбар');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'acntcode', 'brchno', 'curcode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_susp');
    }
};
