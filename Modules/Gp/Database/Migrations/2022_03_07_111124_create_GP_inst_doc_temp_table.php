<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Document Template
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_doc_temp', function (Blueprint $table) {
            $table->id();

            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->comment('Нэр2');
            $table->text('template')->comment('HTML template');
            $table->smallInteger('doctype')->comment('Хөдгөлөх document төрөл: 0 - Print 1 - printable with variables, 2 - printable with form input');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
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
        Schema::dropIfExists('GP_inst_doc_temp');
    }
};
