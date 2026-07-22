<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Шимтгэлийн төрлийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_fee', function (Blueprint $table) {
            $table->id();

            $table->string('feecode', 4)->comment('Шимтгэлийн код');
            $table->string('name', 200)->comment('Шимтгэлийн нэр');
            $table->string('name2', 200)->nullable()->comment('Шимтгэлийн нэр2');
            $table->smallInteger('collmeth')->comment('Суутгах арга: 1 - үлдэгдэл хүрэхгүй үед шимтгэл авахгүй, 2 - үлдэгдэл хүрэхгүй үед боломжтой дүнгээр нь шимтгэл авна, 3 - үлдэгдэл хүрэхгүй үед гүйлгээг бүхэлд нь хийхгүй');
            $table->string('txncode', 10)->comment('Шимтгэлийн гүйлгээний код');
            $table->string('corrcode', 10)->nullable()->comment('засварын гүйлгээний код');
            $table->smallInteger('brchapply')->comment('Шимтгэлийг аль салбарт тооцох эсэх: 1- дансны салбарт, 2 - гүйлгээ хийж байгаа теллерийн салбарт');
            $table->bigInteger('listorder')->default(0)->comment('Жагсаалтын эрэмбэ');
            $table->string('curcode', 3)->nullable()->comment('Валютын код');
            $table->smallInteger('feetype')->default(0)->comment('0 - Бэлэн бусаар авах шимтгэл, 1 - Бэлнээр авах шимтгэл');
            $table->smallInteger('chid')->nullable()->comment('');
            $table->string('description', 200)->nullable()->comment('Тайлбар');
            $table->string('feegroup', 50)->nullable()->comment('Шимтгэлийн грүп');
            $table->smallInteger('sendvat')->default(1)->comment('E-Barimt илгээх эсэх, 0 - Үгүй, 1 - Тийм');
            $table->string('classification_code', 7)->nullable()->default('7113900')->comment('Ангилалын код');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'feecode']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_fee');
    }
};
