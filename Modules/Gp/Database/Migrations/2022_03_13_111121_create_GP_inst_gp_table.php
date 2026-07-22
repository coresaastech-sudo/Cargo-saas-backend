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
        Schema::create('GP_inst_gp', function (Blueprint $table) {
            $table->id();
            $table->string('itemname', 30);
            $table->string('itemdesc', 200)->nullable();
            $table->string('itemdesc2', 200)->nullable();
            $table->string('itemvalue', 2000);
            $table->string('itemadditional', 400)->nullable();
            $table->string('itemadditional2', 400)->nullable();
            $table->smallInteger('itemtype')->default(0)->comment('"Тохиргооны төрөл 0 String 1 Combobox 2 Checkbox 4 Date 5 Number 9 (Hide)');
            $table->string('groupname', 30)->nullable();

            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['itemname', 'instid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_gp');
    }
};
