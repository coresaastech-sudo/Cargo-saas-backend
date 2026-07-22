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
            'GP_inst_branch',
            function (Blueprint $table) {   
                $table->string('taxregion', 5)->comment('Татварын хаяарал')->nullable();          
                $table->string('taxsubregion', 5)->comment('Татварын дэд хаяарал')->nullable();         

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
        // Schema::dropIfExists('GP_inst_branch');
    }
};
