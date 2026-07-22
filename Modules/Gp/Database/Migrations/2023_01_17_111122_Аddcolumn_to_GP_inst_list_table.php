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
            'GP_inst_list',
            function (Blueprint $table) {
                $table->date('billstartdate')->nullable()->comment('Нэхэмжлэх бодогдож эхлэх огноо');
                $table->smallInteger('iscreate_invoice')->default(1)->comment('Нэхэмжлэх үүсгэх эсэх 0-үгүй, 1-тийм');
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
