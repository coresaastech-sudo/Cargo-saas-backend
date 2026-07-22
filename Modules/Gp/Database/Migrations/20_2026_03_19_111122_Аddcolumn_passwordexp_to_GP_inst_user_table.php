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
        Schema::table(
            'GP_inst_user',
            function (Blueprint $table) {
                $table->smallInteger('passwordexp')->default(0)->comment('Нууц үгийн хугацаа дуусах эсэх, 0 - Үгүй, 1 - Тийм');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('GP_inst_user');
    }
};
