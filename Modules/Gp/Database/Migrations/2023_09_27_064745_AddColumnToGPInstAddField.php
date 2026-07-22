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
        Schema::table('GP_inst_add_field', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->comment('Систем дотоод хэрэглээнд ашиглах код');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('GP_inst_add_field', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
