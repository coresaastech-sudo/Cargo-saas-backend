<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Гүйлгээнд уясан шимтгэлийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_txn_fee', function (Blueprint $table) {
            $table->id();
            $table->string('ACTION_CODE', 8)->comment('Гүйлгээний код');
            $table->string('feecode', 4)->comment('Шимтгэлийн код');

            $table->smallinteger('deductcracnt')->default(0)->comment('Шимтгэлийг кредит данснаас авна 1-тийм, 0-үгүй');
            $table->smallinteger('deductdracnt')->default(0)->comment('Шимтгэлийг дебит данснаас авна 1-тийм, 0-үгүй');
            $table->smallinteger('feecalcamount')->default(0)->comment('Аль гүйлгээний дүнгээс шимтгэл тооцох эсэх 0 - кредит данснаас гарч байгаа гүйлгээний дүнгээс 1 - дебит дансанд орж байгаа гүйлгээний дүнгээс');
            $table->string('rtypecode', 3)->nullable()->comment('Ханшийн төрлийн код. Хэрэв шимтгэлийн гүйлгээ нь валютын арилжаатай тохиолдолд авч ашиглах ханшийн төрөл');
            $table->smallinteger('whenapply')->default(0)->comment('Гүйлгээ хийгдэхэд шимтгэл автомат хийгдэх эсэх 0 - Гүйлгээ хийхэд шимтгэл автоматаар авагдана 1 - Энэ нь зөвхөн шимтгэлийн дүнг тооцоолоход ашиглагдана');
            $table->text('formula')->nullable()->comment('Шимтгэлийн томьёо');
            $table->smallinteger('deductlnrepayacnt')->default(0)->comment('Зээлийн төлбөр хийх данснаас шимтгэл авна 1-тийм, 0-үгүй');
            $table->smallinteger('debittxnamount')->default(0)->nullable()->comment('Гүйлгээний дүнгээс шимтгэл хасна 1-тийм, 0-үгүй');
            $table->smallInteger('isbatchfee')->nullable()->default(0)->comment('Багц шимтгэлтэй эсэх 0 - үгүй, 1 - тийм');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв 1 - идэвхтэй, -1 - устсан');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'ACTION_CODE', 'feecode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_txn_fee');
    }
};
