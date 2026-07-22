<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Шимтгэлийн төрөл Гүйлгээний эх сурвалж холбоос
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_fee_source', function (Blueprint $table) {
            $table->id();

            $table->string('feecode', 4)->comment('Шимтгэлийн код');
            $table->string('sourcecode', 4)->comment('Эх сурвалжын код');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'feecode', 'sourcecode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_fee_source');
    }
};
