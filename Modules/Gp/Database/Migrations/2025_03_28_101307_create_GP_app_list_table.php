<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('GP_app_list', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 50)->comment('Аппликейшны нэр');
            $table->string('app_identifier', 20)->comment('Аппликейшны таних дугаар');
            $table->string('app_secret', 80)->comment('Аппликейшны нууц код SHA256 ecrypted');
            $table->json('app_data')->comment('Аппликейшны мэдээлэл');
            $table->smallInteger('statusid')->default(1)->comment('Төлөвийн код, 1-үүссэн, -1-устгасан');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['app_identifier', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_app_list');
    }
};
