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
        Schema::create('GP_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->binary('file');
            $table->string('name');
            $table->string('type', 10);
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('GP_files');
    }
};
