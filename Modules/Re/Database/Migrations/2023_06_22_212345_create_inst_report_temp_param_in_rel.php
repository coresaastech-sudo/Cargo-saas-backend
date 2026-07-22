<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын параметрийн оролт
     *
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp_param_in_rel', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('templateid')->comment('Темп хүснэгтийн id');

            $table->bigInteger('paramid')->comment('Параметер ID');

            $table->bigInteger('inputid')->comment('Параметер оролтын ID');

            $table->smallInteger('statusid')->comment('Төлөв  -1-устсан, 1-идвэхтэй');
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
        Schema::dropIfExists('re_inst_report_temp_param_in');
    }
};
