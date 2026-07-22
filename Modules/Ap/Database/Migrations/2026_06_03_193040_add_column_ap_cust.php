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
        Schema::table('ap_cust', function (Blueprint $table) {
            $table->smallInteger('custtypecode')->nullable()->default(0)->comment('Харилцагчийн төрөл код. 0 - Хувь хүн 1 - Байгууллага');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ap_cust', function (Blueprint $table) {
            $table->dropColumn('custtypecode');
        });
    }
};
