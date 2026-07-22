<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_INVOICE");
        Schema::table('GP_inst_invoice', function (Blueprint $table) {
            $table->string('bankaccountno', 31)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('GP_inst_invoice', function (Blueprint $table) {
            $table->string('bankaccountno', 50)->change();
        });
    }
};
