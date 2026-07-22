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
        Schema::table('ad_perm_report', function (Blueprint $table) {
            $table->unsignedBigInteger('roleid')->comment('Эрхийн дугаар')->nullable();
            $table->dropUnique(['instid', 'statusid', 'AC', 'valuetype', 'userid', 'brchno', 'showbrchno']);
            $table->unique(['instid', 'statusid', 'AC', 'valuetype', 'userid', 'brchno', 'roleid', 'showbrchno']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_perm_report');
    }
};
