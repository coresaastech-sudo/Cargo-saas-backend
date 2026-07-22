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
         DB::statement('ALTER TABLE ap_cust_user RENAME COLUMN "ebarimt_consumerNo" TO "ebarimt_consumerno"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('ap_cust_user', function (Blueprint $table) {
        //     $table->string('ebarimt_consumerNo')->nullable()->comment('Иргэний ebarimt-н бүртгэлийн дугаар');
        // });
    }
};
