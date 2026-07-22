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
        Schema::table('ad_zms_inquiry', function (Blueprint $table) {
            $table->unsignedBigInteger('stmt_id')->nullable()->comment('Дансны хуулганы ID талбар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_zms_inquiry', function (Blueprint $table) {
            $table->dropColumn('stmt_id');
        });
    }
};
