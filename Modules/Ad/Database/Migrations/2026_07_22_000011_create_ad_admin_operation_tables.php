<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_automation_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_automation_rules', 'handler')) {
                $table->string('handler')->nullable()->after('actions');
                $table->string('schedule')->nullable()->after('handler');
                $table->json('settings')->nullable()->after('schedule');
            }
        });

        Schema::table('ad_notification_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_notification_templates', 'name')) {
                $table->string('name', 160)->nullable()->after('template_code');
                $table->json('settings')->nullable()->after('body');
            }
        });

        Schema::table('ad_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_notifications', 'template_code')) {
                $table->string('template_code', 80)->nullable()->after('user_id');
                $table->string('recipient')->nullable()->after('channel');
                $table->string('subject')->nullable()->after('recipient');
                $table->text('error_message')->nullable()->after('payload');
            }
        });

        Schema::create('ad_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('gp_branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_code', 80);
            $table->string('name', 160);
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'operator_code']);
        });

        Schema::create('ad_secret_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('policy_code', 80);
            $table->string('name', 160);
            $table->json('policy')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'policy_code']);
        });

        Schema::create('ad_report_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->string('report_key', 120);
            $table->string('permission_level', 40)->default('view');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'role_id', 'report_key']);
        });

        Schema::create('ad_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('ad_automation_rules')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_receipt_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('receipt_code', 80);
            $table->string('name', 160);
            $table->string('provider', 80)->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'receipt_code']);
        });

        Schema::create('ad_receipt_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('receipt_no', 120)->nullable();
            $table->string('provider', 80)->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_settlement_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('account_code', 80);
            $table->string('name', 160);
            $table->string('account_type', 60)->default('settlement');
            $table->string('account_no', 120)->nullable();
            $table->string('currency', 3)->default('MNT');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'account_code']);
        });

        Schema::create('ad_email_blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('email');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_email_blacklists');
        Schema::dropIfExists('ad_settlement_accounts');
        Schema::dropIfExists('ad_receipt_logs');
        Schema::dropIfExists('ad_receipt_configs');
        Schema::dropIfExists('ad_automation_runs');
        Schema::dropIfExists('ad_report_permissions');
        Schema::dropIfExists('ad_secret_policies');
        Schema::dropIfExists('ad_operators');
    }
};
