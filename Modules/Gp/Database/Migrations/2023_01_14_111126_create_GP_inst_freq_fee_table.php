<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Байгууллагын автомат ажилбарын бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_freq_fee_job', function (Blueprint $table) {
            $table->id();
            $table->string('jobcode', 4)->comment('Ажилбарын код');
            $table->string('name', 200)->comment('Валютын нэр');
            $table->string('name2', 200)->nullable()->comment('Валютын нэр 2');
            $table->string('feecode', 4)->comment('Шимтгэлийн код');
            $table->string('execfreq', 1)->comment('Ажиллах давтамж: M - сар бүр, Q - улирал бүр, B - хагас жилд 1 удаа, Y - жилд 1 удаа');
            $table->string('proctype', 1)->comment('Хэзээ дуудагдах: B - өдрийн нээлт дээр, E - өдрийн хаалт дээр');
            $table->string('rtypecode', 3)->nullable()->comment('Ханшийн төрлийн код. Хэрэв шимтгэлийн гүйлгээ нь валютын арилжаатай тохиолдолд авч ашиглах ханшийн төрөл');
            $table->string('formula', 2000)->nullable()->comment('Томъёо');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'jobcode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_freq_fee_job');
    }
};
