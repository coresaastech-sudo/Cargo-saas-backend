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
            'GP_inst_fee',
            function (Blueprint $table) {
            $table->smallInteger('sendvat')->default(1)->comment('E-Barimt илгээх эсэх, 0 - Үгүй, 1 - Тийм');
            $table->string('classification_code', 7)->nullable()->default('7113900')->comment('eBarimt-н ETAX-н жагсаалтаас авна. Ангилалын код');
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
