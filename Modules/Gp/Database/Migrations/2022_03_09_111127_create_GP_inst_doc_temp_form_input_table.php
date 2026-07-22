<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Application Form Input
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_doc_temp_form_input', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('doctempid')->comment('Formid');
            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->comment('Нэр2');
            $table->string('input')->comment('Input variable');
            $table->smallInteger('forminputtype')->comment('Input төрөл: 1 - input-text, 2 - input-number, 3 - text-area, 4 - dropdown');
            $table->string('dropdowndic', 10)->nullable()->comment('Dropdown ашигалах dictionary');
            $table->integer('length')->comment('авах утгын дээд урт');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'doctempid', 'input']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_doc_temp_form_input');
    }
};
