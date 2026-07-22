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
        Schema::create('ad_ebarimt', function (Blueprint $table) {
            $table->id()->comment('ID дугаар');
            $table->bigInteger('jrno')->comment('Журналын дугаар');
            $table->string('moduleid')->comment('Гүйлгээ хийгдсэн модулын төрөл, Депозит-"dp", Зээл-"ln"');
            $table->date('txndate')->comment('Гүйлгээ хийгдсэн огноо');
            $table->string('curcode', 10)->comment('Гүйлгээний валют');
            $table->string('txncode', 10)->comment('Гүйлгээний код буюу процесс код');
            $table->string('customerno')->nullable()->comment('Харилцагчийн дугаар');
            $table->decimal('amount', 23, 8)->nullable()->comment('Дүн');
            $table->decimal('vat',23, 8)->nullable()->comment('Татвар');
            $table->decimal('cashamount', 23, 8)->nullable()->comment('Бэлэн дүн');
            $table->decimal('noncashamount', 23, 8)->nullable()->comment('Бэлэн бус дүн');
            $table->smallInteger('billtype')->nullable()->comment('Билл төрөл');
            $table->smallInteger('taxtype')->nullable()->comment('Татварын төрөл');
            $table->string('res_billid', 33)->nullable()->comment('Билл дугаар хариу');
            $table->string('res_qrdata', 1000)->nullable()->comment('QR дата');
            $table->string('res_internalcode', 500)->nullable()->comment('Дотоод код хариу');
            $table->date('res_date')->nullable()->comment('Огноо');
            $table->string('res_lottery', 16)->nullable()->comment('Сугалааны дугаар');
            $table->string('res_lotterywarningmsg', 1000)->nullable()->comment('Сугалааны мэссэж');
            $table->string('res_success', 5)->nullable()->comment('-2 Буцаалт');
            $table->string('res_errorcode', 4)->nullable()->comment('1 - Амжилтай хариу');
            $table->string('res_message', 500)->nullable()->comment('Алдаа код');;
            $table->string('res_warningmsg', 500)->nullable()->comment('Хариу мессэж');
            $table->smallInteger('prev_id')->nullable()->comment('Анхааруулга мэссэж');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->smallInteger('statusid')->default(1);
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();
            $table->unique(['instid', 'txndate', 'jrno', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_ebarimt');
    }
};
