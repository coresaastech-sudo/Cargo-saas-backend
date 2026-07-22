<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын цаасны хэмжээ
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp_dim', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->nullable()->comment('Нэр2');
            $table->float('width', 8, 2)->comment('Paper width - A4 - 210mm');
            $table->float('height', 8, 2)->comment('Paper height - A4 - 297mm');

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
        Schema::dropIfExists('re_inst_report_temp_dim');
    }
};
