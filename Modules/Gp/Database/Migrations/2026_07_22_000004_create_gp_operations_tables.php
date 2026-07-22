<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_action_registry', function (Blueprint $table) {
            if (! Schema::hasColumn('gp_action_registry', 'group_code')) {
                $table->string('group_code', 80)->nullable()->after('module_code');
                $table->string('group_name', 160)->nullable()->after('group_code');
                $table->string('icon', 80)->nullable()->after('group_name');
            }
        });

        Schema::create('gp_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('sequence_code', 80);
            $table->string('name', 160);
            $table->string('prefix', 40)->nullable();
            $table->string('suffix', 40)->nullable();
            $table->unsignedInteger('padding')->default(6);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'sequence_code']);
        });

        Schema::create('gp_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('scope_type', 60);
            $table->string('scope_id', 80)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('service_type_code', 80);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'service_type_code']);
        });

        Schema::create('gp_service_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('tariff_code', 80);
            $table->string('name', 160);
            $table->string('service_type', 80)->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->string('currency', 3)->default('MNT');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'tariff_code']);
        });

        Schema::create('gp_service_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('fee_code', 80);
            $table->string('name', 160);
            $table->string('fee_type', 60)->default('fixed');
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('percent', 8, 4)->nullable();
            $table->string('currency', 3)->default('MNT');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'fee_code']);
        });

        Schema::create('gp_system_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 80)->unique();
            $table->string('name', 160);
            $table->string('handler')->nullable();
            $table->string('schedule')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('provider_code', 80);
            $table->string('name', 160);
            $table->string('provider_type', 60);
            $table->string('endpoint')->nullable();
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'provider_code']);
        });

        Schema::create('gp_response_codes', function (Blueprint $table) {
            $table->id();
            $table->string('response_code', 80)->unique();
            $table->string('message', 250);
            $table->string('message2', 250)->nullable();
            $table->unsignedSmallInteger('http_status')->default(400);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_whitelabel_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('brand_code', 80);
            $table->string('name', 160);
            $table->string('logo_path')->nullable();
            $table->json('theme')->nullable();
            $table->string('domain')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'brand_code']);
        });

        Schema::create('gp_mail_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('mail_code', 80);
            $table->string('name', 160);
            $table->string('driver', 60)->default('smtp');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'mail_code']);
        });

        Schema::create('gp_file_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('file_code', 80)->nullable();
            $table->string('name', 180);
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_photo_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('photo_code', 80)->nullable();
            $table->string('name', 180);
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_user_delegates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        foreach (['gp_audit_logs', 'gp_request_logs', 'gp_change_logs', 'gp_error_logs', 'gp_failed_job_logs', 'gp_email_logs'] as $logTable) {
            Schema::create($logTable, function (Blueprint $table) {
                $table->id();
                $table->string('module_code', 20)->nullable();
                $table->string('action_code', 80)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('event')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->string('method', 20)->nullable();
                $table->string('path')->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('error_code')->nullable();
                $table->text('message')->nullable();
                $table->text('trace')->nullable();
                $table->string('job_name')->nullable();
                $table->string('queue')->nullable();
                $table->string('recipient')->nullable();
                $table->string('subject')->nullable();
                $table->string('template_code')->nullable();
                $table->json('request_body')->nullable();
                $table->json('response_body')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('payload')->nullable();
                $table->json('context')->nullable();
                $table->text('exception')->nullable();
                $table->text('error_message')->nullable();
                $table->string('ip_address', 60)->nullable();
                $table->string('user_agent')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['gp_email_logs', 'gp_failed_job_logs', 'gp_error_logs', 'gp_change_logs', 'gp_request_logs', 'gp_audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('gp_user_delegates');
        Schema::dropIfExists('gp_photo_assets');
        Schema::dropIfExists('gp_file_assets');
        Schema::dropIfExists('gp_mail_configs');
        Schema::dropIfExists('gp_whitelabel_configs');
        Schema::dropIfExists('gp_response_codes');
        Schema::dropIfExists('gp_provider_configs');
        Schema::dropIfExists('gp_system_jobs');
        Schema::dropIfExists('gp_service_fees');
        Schema::dropIfExists('gp_service_tariffs');
        Schema::dropIfExists('gp_service_types');
        Schema::dropIfExists('gp_suspensions');
        Schema::dropIfExists('gp_sequences');
    }
};
