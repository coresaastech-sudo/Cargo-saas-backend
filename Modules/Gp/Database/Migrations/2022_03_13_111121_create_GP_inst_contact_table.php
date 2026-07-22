<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Байгууллагын холбоо барих ажилтаны бүртгэл
     *
     * @return void
     */
    public function up()
    {
        Schema::create('GP_inst_contact', function (Blueprint $table) {
            $table->id();
            $table->string('contacttype', 30)->comment('Албан тушаалын төрөл');
            $table->string('fname', 50)->comment('Нэр');
            $table->string('lname', 50)->nullable()->comment('Овог');
            $table->string('email', 50)->comment('ИМэйл');
            $table->string('phone', 20)->nullable()->comment('Утас');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгч');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
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
        Schema::dropIfExists('GP_inst_contact');
    }
};
