<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлан авах боломж бүхий хүснэгтийн талбарууд
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_table_field', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->nullable()->comment('Нэр2');
            $table->string('fieldname', 128)->comment('Тухайн талбарт хандах нэр');
            $table->string('description', 500)->nullable()->comment('Тухайн талбарын тайлбар');
            $table->smallInteger('ispkey')->comment('Primary key мөн эсэх');
            $table->smallInteger('type')->comment('Input төрөл: 1 - String, 2 - Number, 3 - Boolean, 4 - Date');
            $table->bigInteger('tableid')->comment('Хүснэгтийн ID');

            $table->smallInteger('statusid')->comment('Төлөв -1-устсан, 1-идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('re_inst_table_field');
    }
};
