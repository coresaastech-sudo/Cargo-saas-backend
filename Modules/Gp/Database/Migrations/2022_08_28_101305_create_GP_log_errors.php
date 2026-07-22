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
        Schema::create('log_errors', function (Blueprint $table) {
            $table->id()->comment('ID дугаар');
            $table->text('message')->nullable()->comment('Алдааны мессеж');
            $table->text('context')->nullable()->comment('Алдааны stack trace');
            $table->string('loglevel', 255)->comment('Төвшин');
            $table->string('level_name', 255)->comment('Төвшний нэр');
            $table->string('channel', 255)->comment('Суваг');
            $table->string('record_datetime', 255)->nullable();
            $table->text('extra')->nullable();
            $table->text('formatted')->nullable();
            $table->string('remote_addr', 255)->nullable()->comment('Хэрэглэгчийн IP хаяг');
            $table->string('user_agent', 255)->nullable()->comment('Хэрэглэгчийн User agent');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Үүсгэсэн хэрэглэгч');
            $table->string('request_uri', 500)->nullable()->comment('Алдаа үүссэн хаяг');
            $table->string('request_method', 100)->nullable()->comment('Хүсэлтийн метод');
            $table->text('request_content')->nullable()->comment('Хүсэлтийн агуулга');
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
        Schema::dropIfExists('log_errors');
    }
};
