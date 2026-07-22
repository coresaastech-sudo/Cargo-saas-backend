<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit log table
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('GP_audit_log');

        Schema::create('GP_audit_log', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('userid')->nullable()->comment('Өөрчлөлт хийсэн хэрэглэгчийн id');
            $table->bigInteger('instid')->nullable()->comment('Өөрчлөлт хийсэн хэрэглэгчийн байгууллагийн id');
            $table->string('ip', 20)->nullable();
            $table->string('AC', 8)->nullable();
            $table->string('parent_objectid', 100)->nullable()->comment('Үндсэн бичилтийн pk');
            $table->string('objectid', 100)->comment('Үндсэн бичилтийн дугаар');
            $table->string('object_type', 100)->comment('Үндсэн бичилтийн төрөл');
            $table->string('action_type', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('GP_audit_log');
    }
};