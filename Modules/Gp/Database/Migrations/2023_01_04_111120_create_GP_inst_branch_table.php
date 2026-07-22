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
        Schema::create('GP_inst_branch', function (Blueprint $table) {
            $table->string('brchno', 4);
            $table->string('name', 200);
            $table->string('name2', 200)->nullable();
            $table->string('dirname', 50)->nullable();
            $table->string('dirname2', 50)->nullable();
            $table->date('begindate');
            $table->string('phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->smallInteger('isonline')->default(1);
            $table->string('bankcode', 6)->nullable();
            $table->string('blevel', 10)->nullable();
            $table->string('biccode', 30)->nullable();
            $table->smallInteger('doestrade')->default(0);
            $table->smallInteger('listorder')->nullable();
            $table->bigInteger('state')->nullable();
            $table->bigInteger('region')->nullable();
            $table->string('subregion', 10)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->string('w3w', 50)->nullable();
            $table->bigInteger('instid');
            $table->smallInteger('statusid')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->primary(['brchno', 'instid', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_branch');
    }
};
