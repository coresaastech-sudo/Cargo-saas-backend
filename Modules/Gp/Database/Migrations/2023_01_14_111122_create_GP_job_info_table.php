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
        // Schema::dropIfExists('GP_job_infos');
        Schema::create('GP_job_infos', function (Blueprint $table) {
            $table->id();
            $table->string('job');
            $table->integer('jobcount')->default(0)->comment('Идэвхтэй жобын тоо');
            $table->integer('successcount')->default(0)->comment('Амжилттай ажилласан жобын тоо');
            $table->integer('statusid');
            $table->dateTime('lastexecdate')->nullable()->comment('Сүүлд ажилласан огноо');
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
        Schema::dropIfExists('GP_job_infos');
    }
};
