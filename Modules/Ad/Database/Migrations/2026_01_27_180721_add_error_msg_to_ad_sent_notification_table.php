<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ad_sent_notification', function (Blueprint $table) {
            $table->text('error_msg')->nullable()->comment('Алдааны мэдээлэл');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_sent_notification', function (Blueprint $table) {
            $table->dropColumn('error_msg');
        });
    }
};
