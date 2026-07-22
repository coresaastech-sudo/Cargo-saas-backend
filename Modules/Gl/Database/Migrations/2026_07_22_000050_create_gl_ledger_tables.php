<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_account_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('group_code', 80);
            $table->string('name', 160);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('account_code', 80);
            $table->string('name', 160);
            $table->string('account_type', 80)->nullable();
            $table->string('currency', 3)->default('MNT');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('chart_code', 80);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('gp_branches')->nullOnDelete();
            $table->string('txn_no', 120)->nullable();
            $table->string('txn_type', 80)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('MNT');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('gl_posting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('posting_code', 80);
            $table->string('name', 160);
            $table->string('handler')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_report_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('report_code', 80);
            $table->string('name', 160);
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_report_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_config_id')->constrained('gl_report_configs')->cascadeOnDelete();
            $table->string('column_code', 80);
            $table->string('name', 160);
            $table->text('expression')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gl_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_config_id')->nullable();
            $table->json('parameters')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_report_runs');
        Schema::dropIfExists('gl_report_columns');
        Schema::dropIfExists('gl_report_configs');
        Schema::dropIfExists('gl_posting_rules');
        Schema::dropIfExists('gl_transactions');
        Schema::dropIfExists('gl_charts');
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('gl_account_groups');
    }
};
