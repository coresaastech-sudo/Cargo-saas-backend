<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cr_cust_contact', function (Blueprint $table) {
            if (!Schema::hasColumn('cr_cust_contact', 'description')) {
                $table->string('description', 300)->nullable()->comment('Тайлбар');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('cr_cust_contact', function (Blueprint $table) {
            if (Schema::hasColumn('cr_cust_contact', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
