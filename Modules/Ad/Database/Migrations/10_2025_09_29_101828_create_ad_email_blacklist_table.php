<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * e-mail хар жагсаалт
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_email_blacklist', function (Blueprint $table) {

            $table->id();
            $table->string('emailaddress', 60)->nullable()->comment('Имэйл');
            $table->datetime('lastupdatetime')->comment('Бүртгэгдсэн огноо');
            $table->string('reason', 100)->comment('Бүртгэгдсэн шалтгаан');
            $table->string('desc', 200)->comment('Гүйлгээний утга');
            $table->string('source', 100)->comment('Бүртгэл хийсэн эх сурвалж');
            $table->smallInteger('statusid')->comment('Төлөв 1 - Идвэхтэй, 0 - Идвэхгүй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртэгсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->unique(['emailaddress', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_email_blacklist');
    }
};
