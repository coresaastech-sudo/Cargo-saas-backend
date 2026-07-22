<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын загвар
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp', function (Blueprint $table) {
            $table->id();

            $table->string('ACTION_CODE')->comment('Процес код');

            $table->string('name', 200)->comment('Нэр');
            $table->string('name2', 200)->nullable()->comment('Нэр2');

            $table->bigInteger('dimensionid')->comment('Dimension table id тайлангын цаасны хэмжээ');

            $table->smallInteger('orientation')->comment('Чиглэл - 1-Portrait, 2-Landscape');
            $table->string('pagemargin')->comment('Цаасны маржин')->nullable();

            $table->smallInteger('hasheader')->comment('Цаасны толгой хэсэгтэй эсэх 1-тийм, 0-үгүй')->default(0);
            $table->string('headersize')->comment('Цаасны толгой хэсэг')->nullable();
            $table->smallInteger('headerrepeat')->comment('Цаасны толгой хуудас болгон дээр давтагдах эсэх 1-тийм, 0-үгүй')->default(0);

            $table->smallInteger('hasfooter')->comment('Цаасны хөл хэсэгтэй эсэх 1-тийм, 0-үгүй')->default(0);
            $table->string('footersize')->comment('Цаасны хөл хэсгийн хэмжээ')->nullable();
            $table->smallInteger('footerrepeat')->comment('Цаасны толгой хуудас болгон дээр давтагдах эсэх 1-тийм, 0-үгүй')->default(0);

            $table->string('contentheight')->comment('Дизайн хийж байгаа талбарын өндөр. Энэ нь хэвлэх цаасны өндөрт нөлөөлөхгүй.')->nullable();

            $table->string('font')->comment('Тайлан үүсгэх үсгийн фонт 1-Times new roman 2-Calibri 3-Ariel')->default(1);

            $table->smallInteger('exporttype')->comment('Default гаргах төрөл 1-pdf, 2-excel, 3-html');

            $table->string('module')->comment('Template ангилалд ашиглах утга');
            $table->string('groupid', 2)->nullable()->comment('Тайлан бүлэглэх id тогтмолын бүртгэлээс авна.');

            $table->smallInteger('statusid')->comment('Төлөв  -1-устсан, 1-идвэхтэй');
            $table->smallInteger('version')->nullable()->default(1);
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
        Schema::dropIfExists('re_inst_report_temp');
    }
};
