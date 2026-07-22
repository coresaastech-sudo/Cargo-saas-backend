<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Валют шимтгэлийн төрлийн шимтгэлийн шатлалын бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_formula', function (Blueprint $table) {
            $table->id();

            $table->string('name', 250)->comment('Нэр');
            $table->string('name2', 250)->nullable()->comment('Нэр 2');

            $table->smallInteger('type')->comment('Томьёоны төрөл 1 - query, 2 - formula');
            $table->text('formula')->comment('Томьёо эсвэл өгөгдлийн сангийн query');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
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
        Schema::dropIfExists('GP_inst_fee_rate');
    }
};
