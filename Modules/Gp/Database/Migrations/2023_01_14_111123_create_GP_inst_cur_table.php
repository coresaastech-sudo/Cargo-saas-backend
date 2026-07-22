<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Валютын бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_cur', function (Blueprint $table) {
            $table->id();
            $table->string('curcode', 3)->comment('Валютын код');
            $table->string('name', 200)->comment('Валютын нэр');
            $table->string('name2', 200)->nullable()->comment('Валютын нэр 2');
            $table->decimal('avgrate', 23, 8)->default(0)->comment('Албан ханш');
            $table->string('gl', 3)->comment('ЕД дансны код');
            $table->bigInteger('listorder')->default(0)->comment('Жагсаалтын эрэмбэ');
            $table->string('margintype', 1)->nullable()->comment('хэлбэлзэлийн төрөл, F-дүнгээр, P-процент');
            $table->decimal('marginup', 23, 8)->default(0)->comment('дээд хаязгаар');
            $table->decimal('margindown', 23, 8)->default(0)->comment('доод хязгаар');
            $table->decimal('avgrateend', 23, 8)->default(0)->comment('хаалтын албан ханш');
            $table->decimal('midrate', 23, 8)->default(0)->comment('мидиан ханш');
            $table->decimal('marketrate', 23, 8)->default(0)->comment('зах зээлийн ханш');
            $table->decimal('endrate', 23, 8)->nullable()->comment('хаалтын зээлийн ханш');
            $table->smallinteger('yeslimit')->default(0)->comment('limit байх эсэх ( 0 -no, 1 - yes)');
            $table->smallinteger('ismetal')->default(0)->comment('metal мөн эсэх ( 0 - no, 1 - yes)');
            $table->smallinteger('isbase')->default(0)->comment('энэ суурь валют мөн эсэх (mnt) ( 0 - no, 1 - yes)');
            $table->smallinteger('ismain')->default(0)->comment('энэ үндсэн валют мөн эсэх (usd) ( 0 -no, 1 - yes)');
            $table->smallinteger('valuedateterm')->default(0)->comment('хүчинтэй өдөр');
            $table->smallInteger('showsidemenu')->default(1)->comment('Терминалд харагдах эсэх ( 0 -No, 1 - Yes)');
            $table->smallInteger('showonline')->default(0)->comment('Онлайн банкинд харагдах эсэх ( 0 -No, 1 - Yes)');

            $table->string('equivacct', 16)->nullable()->comment('Спот позицийн эквивалент ЕД данс');
            $table->string('fxprof', 16)->nullable()->comment('Арилжааны орлогын ЕД данс');
            $table->string('fxloss', 16)->nullable()->comment('Арилжааны зарлагын ЕД данс');
            $table->string('rvprof', 16)->nullable()->comment('Ханшийн тэгшитгэлийн орлогын ЕД данс');
            $table->string('rvloss', 16)->nullable()->comment('Ханшийн тэгшитгэлийн зарлагын ЕД данс');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'curcode', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_cur');
    }
};
