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
        Schema::create('ad_ebarimt_ACTION_CODE', function (Blueprint $table) {
            $table->id();
            $table->string('ACTION_CODE', 10)->comment('ИБаримт бүртгэл хийгдэх процесс код /уг код дээр хийгдсэн гүйлгээнд ибаримт бүртгэнэ.');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв 0-идэвхгүй, 1-идэвхтэй, -1-устагсан');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->string('parent_ACTION_CODE', 10)->nullable()->comment('Эцэг процесс код, гүйлгээг багцлахад ашиглана');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
            $table->unique(['instid', 'ACTION_CODE', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_ebarimt_ACTION_CODE');
    }
};