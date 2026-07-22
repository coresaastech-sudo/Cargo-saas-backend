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
        Schema::create('gl_chart', function (Blueprint $table) {

            $table->string('acntno', 14)->comment('Дансны дугаар');
            $table->string('name', 100)->comment('Дансны нэр');
            $table->string('name2', 100)->nullable()->comment('Дансны нэр хоёрдогч хэлээр');
            $table->smallInteger('listorder')->default(0)->comment('Дэс дугаар');
            $table->smallInteger('statusid')->comment('Төлөв 1 - идэвхтэй, 0 - хаагдсан');

            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->primary([ 'instid', 'acntno', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_chart');
    }
};
