<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын загварыг параметртэй холбох
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp_content', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('templateid')->comment('Template ID');
            $table->smallInteger('type')->comment('Төрөл - 1-Content, 2-Table, 3-Frame, 4-Child');
            $table->string('contentname')->comment("Content Name");
            $table->text('source')->comment('Үндсэн утга');
            $table->smallInteger('position')->comment('байрлах төрөл - 1 - header, 2 - content, 3 - footer');

            $table->bigInteger('parentid')->comment('Эцэг content id')->nullable();

            $table->smallInteger('richtext')->comment('Rich Text ашиглах эсэх - 0 - үгүй, 1 - тийм');
            $table->smallInteger('orientation')->comment('Чиглэл 0 - хэвийн, 1 - хэвтээ');
            $table->bigInteger('x')->comment('X тэнхлэгийн дагуур байрлал')->nullable();
            $table->bigInteger('y')->comment('Y тэнхлэгийн дагуур байрлал')->nullable();
            $table->string('contentmargin', 256)->comment('Бусад элэментээсээ авах зай')->nullable();

            $table->string('height')->comment('Өндөр 120px')->nullable();
            $table->string('width')->comment('Өргөн 120px')->nullable();

            $table->smallInteger('bordertypes')->comment('Хүснэгтэн өгөгдөлийн хүрээний төрөл - 0 - Байхгүй, 1 - Grid, 2 - Frame and Row, 3 - Frame, 4 - Row')->nullable();
            $table->string('bordercolor', 8)->comment('Хүснэгтийн хүрээний өнгө.')->nullable();
            $table->smallInteger('borderwidth')->comment('Хүснэгтэн өгөгдөлийн хүрээний төрөл - 0 - Байхгүй, 1 - Grid, 2 - Frame and Row, 3 - Frame, 4 - Row')->nullable();
            $table->string('highlightcolor', 24)->comment('Хүснэгтийн хөл толгойн өнгө')->nullable();
            $table->string('maincolor', 24)->comment('Хүснэгтийн өгөдлийн өнгө')->nullable();
            $table->string('alternativecolor', 24)->comment('Хүснэгтийн өгөгдлийн солигдох өнгө')->nullable();
            $table->string('tableheaderrepeat', 24)->comment('Хүснэгтийн толгой хуудас болгон дээр давтагдах эсэх')->nullable();

            $table->string('colcount')->comment('Column count')->nullable();
            $table->bigInteger('relatedparamid')->comment('Холбосон парам')->nullable();
            $table->string('colwidth')->comment('Хүснэгтийн багнуудын уртууд')->nullable();
            $table->smallInteger('align')->comment('Хүснэгтийн утга аль талдаа байрлах нь 1 - left 2 - center 3 - right')->nullable();

            $table->smallInteger('headerfontsize')->comment('Хүснэгтийн толгойн Font хэмжээ')->nullable();
            $table->smallInteger('datafontsize')->comment('Хүснэгтийн толгойн Font хэмжээ')->nullable();

            $table->string('textcolor')->comment('Хүснэгтийн нүдэнд бичигдсэн үгийн өнгө')->nullable();
            $table->smallInteger('verticalalign')->comment('Босоо байрлалын утга 1 - top 2 - middle 3 - bottom')->default(2);
            $table->string('hasfooter')->comment('Хөл хэсэгтэй эсэх')->nullable();
            $table->string('cellexpression')->comment('Хөл хэсгийн excel бодолтын хэсэг')->nullable();

            $table->string('frameinfo')->comment('Хэрвээ өгөгдөл frame төрөл бол frame бүтэцийн мэдээлэл')->nullable();
            $table->string('framepos')->comment('Бүтэц дээр байрлах байрлалын мэдээлэл')->nullable();

            $table->smallInteger('excelshift')->comment('Excel export дээр тайлан зэрэгцүүлэн гаргахад ашиглана')->nullable();

            $table->smallInteger('hasheader')->comment('Тайлангын толгойтой эсэх 1-тийм 0-үгүй')->nullable();

            $table->bigInteger('listorder')->comment('байрлал')->nullable();

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
        Schema::dropIfExists('re_inst_report_temp_content');
    }
};
