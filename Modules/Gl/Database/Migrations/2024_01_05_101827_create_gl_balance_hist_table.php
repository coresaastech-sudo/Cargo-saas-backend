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
        Schema::create('gl_balance_hist', function (Blueprint $table) {

            $table->string('branch', 6)->comment('Салбарын дугаар');
            $table->string('unit', 4)->default('0000')->comment('Нэгж дугаар');
            $table->string('account', 16)->comment('Дансны дугаар');
            $table->string('currency', 3)->comment('Валютын код');
            $table->integer('year')->comment('Жил');
            $table->decimal('obal', 23, 8)->default(0)->comment('Эхлэл баланс');
            $table->decimal('dt01', 23, 8)->default(0)->comment('Дебит дүн');
            $table->decimal('ct01', 23, 8)->default(0)->comment('Кредит дүн');
            $table->decimal('dt02', 23, 8)->default(0)->comment('');
            $table->decimal('ct02', 23, 8)->default(0)->comment('');
            $table->decimal('dt03', 23, 8)->default(0)->comment('');
            $table->decimal('ct03', 23, 8)->default(0)->comment('');
            $table->decimal('dt04', 23, 8)->default(0)->comment('');
            $table->decimal('ct04', 23, 8)->default(0)->comment('');
            $table->decimal('dt05', 23, 8)->default(0)->comment('');
            $table->decimal('ct05', 23, 8)->default(0)->comment('');
            $table->decimal('dt06', 23, 8)->default(0)->comment('');
            $table->decimal('ct06', 23, 8)->default(0)->comment('');
            $table->decimal('dt07', 23, 8)->default(0)->comment('');
            $table->decimal('ct07', 23, 8)->default(0)->comment('');
            $table->decimal('dt08', 23, 8)->default(0)->comment('');
            $table->decimal('ct08', 23, 8)->default(0)->comment('');
            $table->decimal('dt09', 23, 8)->default(0)->comment('');
            $table->decimal('ct09', 23, 8)->default(0)->comment('');
            $table->decimal('dt10', 23, 8)->default(0)->comment('');
            $table->decimal('ct10', 23, 8)->default(0)->comment('');
            $table->decimal('dt11', 23, 8)->default(0)->comment('');
            $table->decimal('ct11', 23, 8)->default(0)->comment('');
            $table->decimal('dt12', 23, 8)->default(0)->comment('');
            $table->decimal('ct12', 23, 8)->default(0)->comment('');
            $table->decimal('dt13', 23, 8)->default(0)->comment('');
            $table->decimal('ct13', 23, 8)->default(0)->comment('');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->primary([ 'instid', 'branch', 'unit', 'account', 'currency', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_balance_hist');
    }
};
