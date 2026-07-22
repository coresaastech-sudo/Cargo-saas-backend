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
        Schema::create('ad_login_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userid');
            $table->string('agent', 255);
            $table->string('device_ip', 20);
            $table->string('channel', 10);
            $table->string('deviceid', 100);
            $table->string('devicename', 100);

            $table->smallInteger('statusid')->default(0)->comment('Төлөв -1 - устсан, 1 - идэвхтэй, 0 - идэвхгүй');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
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
        Schema::dropIfExists('ad_login_activity_log');
    }
};
