<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Document Template Variables
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_doc_temp_var', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('doctempid')->comment('');
            $table->string('title', 128)->comment('Нэр');
            $table->string('variable', 32)->comment('Хайлт хийх утга');
            $table->smallInteger('tempvartype')->comment('Хувьсагчийн төрөл: 1 - string, 2 - numeric, 3 - Boolean');
            $table->integer('length')->comment('авах утгын дээд урт');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'doctempid', 'variable']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_doc_temp_var');
    }
};
