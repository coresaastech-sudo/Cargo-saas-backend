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
        Schema::table('GP_const', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'instid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('GP_const', function (Blueprint $table) {
            $table->dropUnique(['code', 'instid']);
            $table->unique(['code']);
        });
    }

};
