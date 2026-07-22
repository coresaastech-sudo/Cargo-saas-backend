<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Валют шимтгэлийн төрөл. Шимтгэл валют тус бүрээр бүртгэсэн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_fee_cur', function (Blueprint $table) {
            $table->id();
            $table->string('feecode', 4)->comment('Шимтгэлийн код');
            $table->string('curcode', 3)->nullable()->comment('Валютын код');
            $table->smallInteger('calcmeth')->comment('Шимтгэл тооцох арга 1- тогтмол хувь, 2 - тогтмол хэмжээгээр, 3 - шатлалтай, 4 - Томъёо, 5 - шатлалтай (Use Loan count)');

            $table->decimal('perrate', 23, 8)->default(0)->comment('Шимтгэлийн хувь тогтмол');
            $table->decimal('flatrate', 23, 8)->default(0)->comment('Шимтгэлийн хэмжээ тогтмол');
            $table->decimal('minfee', 23, 8)->default(0)->comment('Шимтгэлийн доод хэмжээ');
            $table->decimal('maxfee', 23, 8)->default(0)->comment('Шимтгэлийн дээд хэмжээ');
            $table->string('feecurcode', 3)->nullable()->comment('Шимтгэлийн валютын код');
            $table->text('formula')->nullable()->comment('Шимтгэлийн томьёо');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'feecode', 'curcode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_fee_cur');
    }
};
