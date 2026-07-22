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
        Schema::create('GP_inst_cur_rate_hist', function (Blueprint $table) {
            $table->id();
            $table->string('rtypecode', 3);
            $table->string('curcode', 3);
            $table->decimal('salerate', 23, 8)->default(0);
            $table->decimal('buyrate', 23, 8)->default(0);
            $table->date('date');
            $table->smallInteger('statusid')->default(1);
            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('GP_inst_cur_rate_hist');
    }
};
