<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit log detail table
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('GP_audit_log_detail');
        
        Schema::create('GP_audit_log_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('audit_logid')->comment('Тухайн audit log-ийн id');
            $table->string('fieldname', 100)->comment('Тухайн талбарын нэр');
            $table->text('new_val')->nullable()->comment('Тухайн талбарын шинэ утга');
            $table->text('old_val')->nullable()->comment('Тухайн талбарын хуучин утга');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('GP_audit_log_detail');
    }
};