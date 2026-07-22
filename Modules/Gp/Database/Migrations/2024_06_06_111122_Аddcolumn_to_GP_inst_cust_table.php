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
                $table->integer('tokenlimit')->default(1)->comment('Хэрэглэгчид байж болох токенын тоо');      

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
