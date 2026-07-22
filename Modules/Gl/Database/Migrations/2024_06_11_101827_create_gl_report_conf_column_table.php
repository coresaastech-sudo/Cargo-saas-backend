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
        Schema::create('gl_report_conf_column', function (Blueprint $table) {

            $table->id()->comment('Дугаар');
            $table->unsignedBigInteger('conf_detail_id')->comment('Тайлангын дэлгэрэнгүй тохиргооны ID');
            $table->smallInteger('columnidx')->default(0)->comment('Баганын дугаарлалт');
            $table->string('acntno', 14)->comment('Дансны дугаар');
            $table->smallInteger('statusid')->comment('Төлөв 1 - идэвхтэй, -1 - устгагдсан');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['instid','statusid','conf_detail_id','columnidx','acntno']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_report_conf_column');
    }
};
