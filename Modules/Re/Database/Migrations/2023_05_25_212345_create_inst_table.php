<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлан гаргах боломжтой хүснэгтийн жагсаалт
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->nullable()->comment('Нэр2');
            $table->string('tablename', 128);
            $table->string('description', 500)->nullable();

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
        Schema::dropIfExists('re_inst_tables');
    }
};
