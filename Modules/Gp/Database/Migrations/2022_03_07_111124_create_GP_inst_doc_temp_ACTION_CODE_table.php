<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Template and Process code Relation
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_doc_temp_ACTION_CODE', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('doctempid')->comment('');
            $table->string('ACTION_CODE', 8)->comment('Гүйлгээний код');
            $table->smallInteger('response_type')->comment('Хариу буцаах төрөл: 1 - print, 2 - pdf');

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
        Schema::dropIfExists('GP_inst_doc_temp_ACTION_CODE');
    }
};
