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
        Schema::create('gl_daily_bal', function (Blueprint $table) {


            $table->string('branch', 6)->comment('Салбарын дугаар');
            $table->string('unit', 4)->default('0000')->comment('Нэгж дугаар');
            $table->string('account', 16)->comment('Дансны дугаар');
            $table->string('currency', 3)->comment('Валютын код');
            $table->integer('year')->comment('Жил');
            $table->integer('period')->comment('Сар');
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
            $table->decimal('dt14', 23, 8)->default(0)->comment('');
            $table->decimal('ct14', 23, 8)->default(0)->comment('');
            $table->decimal('dt15', 23, 8)->default(0)->comment('');
            $table->decimal('ct15', 23, 8)->default(0)->comment('');
            $table->decimal('dt16', 23, 8)->default(0)->comment('');
            $table->decimal('ct16', 23, 8)->default(0)->comment('');
            $table->decimal('dt17', 23, 8)->default(0)->comment('');
            $table->decimal('ct17', 23, 8)->default(0)->comment('');
            $table->decimal('dt18', 23, 8)->default(0)->comment('');
            $table->decimal('ct18', 23, 8)->default(0)->comment('');
            $table->decimal('dt19', 23, 8)->default(0)->comment('');
            $table->decimal('ct19', 23, 8)->default(0)->comment('');
            $table->decimal('dt20', 23, 8)->default(0)->comment('');
            $table->decimal('ct20', 23, 8)->default(0)->comment('');
            $table->decimal('dt21', 23, 8)->default(0)->comment('');
            $table->decimal('ct21', 23, 8)->default(0)->comment('');
            $table->decimal('dt22', 23, 8)->default(0)->comment('');
            $table->decimal('ct22', 23, 8)->default(0)->comment('');
            $table->decimal('dt23', 23, 8)->default(0)->comment('');
            $table->decimal('ct23', 23, 8)->default(0)->comment('');
            $table->decimal('dt24', 23, 8)->default(0)->comment('');
            $table->decimal('ct24', 23, 8)->default(0)->comment('');
            $table->decimal('dt25', 23, 8)->default(0)->comment('');
            $table->decimal('ct25', 23, 8)->default(0)->comment('');
            $table->decimal('dt26', 23, 8)->default(0)->comment('');
            $table->decimal('ct26', 23, 8)->default(0)->comment('');
            $table->decimal('dt27', 23, 8)->default(0)->comment('');
            $table->decimal('ct27', 23, 8)->default(0)->comment('');
            $table->decimal('dt28', 23, 8)->default(0)->comment('');
            $table->decimal('ct28', 23, 8)->default(0)->comment('');
            $table->decimal('dt29', 23, 8)->default(0)->comment('');
            $table->decimal('ct29', 23, 8)->default(0)->comment('');
            $table->decimal('dt30', 23, 8)->default(0)->comment('');
            $table->decimal('ct30', 23, 8)->default(0)->comment('');
            $table->decimal('dt31', 23, 8)->default(0)->comment('');
            $table->decimal('ct31', 23, 8)->default(0)->comment('');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->primary([ 'instid', 'branch', 'unit', 'account', 'currency', 'year', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_daily_bal');
    }
};
