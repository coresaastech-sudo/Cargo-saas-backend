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
        Schema::create('GP_provider_conf', function (Blueprint $table) {
            $table->id()->comment('Дугаарлалт');
            $table->string('code')->comment('Систем дотоод хэрэглээнд ашиглах код');
            $table->string('name', 100)->comment('Нэр');
            $table->string('name2', 100)->nullable()->comment('Нэр хоёрдагч хэл');
            $table->string('connid', 10)->comment('Холболтын тохиргооны дугаар');
            $table->string('typeid', 10)->comment('Төрлийн код, Тогтмолын бүртгэлээс авна.');
            $table->text('config', 4000)->nullable()->comment('Тохиргооны JSON');
            $table->string('descr', 500)->nullable()->comment('Дэлгэрэнгүй тайлбар');
            $table->string('sec1', 200)->nullable()->comment('Нууц үг1');
            $table->string('sec2', 200)->nullable()->comment('Нууц үг2');

            $table->smallInteger('statusid')->comment('Төлөв  -1-устсан, 1-идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
            $table->unique(['instid','code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_provider_conf');
    }
};
