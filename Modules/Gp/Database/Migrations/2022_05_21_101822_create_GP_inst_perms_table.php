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
        Schema::create('GP_inst_perms', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instid');
            $table->string('moduleid', 20)->nullable();
            $table->string('AC', 8);
            $table->smallInteger('isadmin')->nullable();
            $table->smallInteger('statusid')->default(1);
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
        Schema::dropIfExists('GP_inst_perms');
    }
};
