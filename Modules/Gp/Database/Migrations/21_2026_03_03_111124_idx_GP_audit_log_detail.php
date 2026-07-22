<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('GP_audit_log_detail', function (Blueprint $table) {
            $table->index('audit_logid', 'idx_GP_audit_log_detail_audit_logid');
        });
    }

    public function down()
    {
        Schema::table('GP_audit_log_detail', function (Blueprint $table) {
            $table->dropIndex('idx_GP_audit_log_detail_audit_logid');
        });
    }
};
