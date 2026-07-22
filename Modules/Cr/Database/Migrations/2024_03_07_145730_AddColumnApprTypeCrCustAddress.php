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
        Schema::table('cr_cust_address', function (Blueprint $table) {
            $table->smallInteger('apprtypecode')->nullable()->comment('Харилцагчийн орон сууцны төрөл');
       });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cr_cust_address', function (Blueprint $table) {
            $table->dropColumn('apprtypecode');
        });
    }
};
