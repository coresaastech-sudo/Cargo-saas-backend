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
        Schema::create('GP_inst_eod_steps', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('orderno')->comment('Алхамын дугаар')->default(0);
            $table->string('name', 200)->comment('Алхамын нэр');
            $table->string('name2', 200)->comment('Алхамын нэр2')->nullable();
            $table->string('stepdesc', 500)->comment('Нэмэлт тайлбар')->nullable();
            $table->string('controller', 100)->comment('Класс')->nullable();
            $table->string('function', 100)->comment('Функц')->nullable();
            $table->string('exturl', 100)->comment('Функц нь гадны системийн хаяг, function дээр post or get тодорхойлно.')->nullable();
            $table->smallInteger('statusid')->comment('Ажиллах төлөв 1 - идэвхтэй 0 - идэвхгүй')->default(0);
            $table->string('runfreq', 1)->comment('Ажиллах давтамж D - өдөр M - сар Q - улирал H - хагас жил Y - жил');
            $table->smallInteger('modifyopt')->comment('Засах боломжтой эсэх 0 - ямар ч өөрчлөлт хийх боломжгүй 1 - зөвхөн ажиллах төлөв
            өөрчлөх боломжтой 9 - бүгдийг өөрчлөх, устгах боломжтой')->default(0);
            $table->smallInteger('proctype')->comment('0 - fixed 1 - user defined function 2 - user defined sql')->default(0);
            $table->string('sqlscript', 2000)->comment('SQL скрипт')->nullable();
            $table->smallInteger('runmonth')->comment('Ажиллах сар')->default(0);
            $table->smallInteger('runday')->comment('Ажиллах өдөр')->default(0);
            $table->smallInteger('sendsms')->comment('СМС мэдэгдэл илгээх 1 - илгээх, 0 - үгүй')->default(0);
            $table->smallInteger('sendemail')->comment('Имайл мэдэгдэл илгээх 1 - илгээх, 0 - үгүй')->default(0);

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'orderno']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_eod_steps');
    }
};
