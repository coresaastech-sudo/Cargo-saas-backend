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
        Schema::table('log_changes', function (Blueprint $table) {
            $table->bigInteger('instid')->nullable()->comment('Байгууллагын дугаар');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('log_changes', function (Blueprint $table) {
            $table->dropColumn('instid');
        });
    }
};
