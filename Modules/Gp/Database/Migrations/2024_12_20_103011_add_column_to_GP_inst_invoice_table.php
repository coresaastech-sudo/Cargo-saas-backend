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
        Schema::table('GP_inst_invoice', function (Blueprint $table) {
            $table->decimal('apfee', 19, 2)->default(0)->comment('НӨАТ бодохгүй шимтгэлийн дүн');
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
            $table->dropColumn('apfee');
        });
    }
};
