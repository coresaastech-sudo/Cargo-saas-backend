<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_customer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_level', 40)->default('standard');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('service_code', 80);
            $table->string('name', 160);
            $table->string('service_type', 80)->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('status', 20)->default('unread');
            $table->timestamps();
        });

        Schema::create('ap_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('faq_code', 80);
            $table->text('question');
            $table->text('answer')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_private_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('resource_code', 80);
            $table->string('name', 160);
            $table->string('resource_type', 60)->nullable();
            $table->string('path')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_customer_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('contract_no', 120);
            $table->string('contract_type', 80)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('terms')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_stop_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('service_code', 80)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('ap_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('token_name', 120);
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_access_tokens');
        Schema::dropIfExists('ap_stop_services');
        Schema::dropIfExists('ap_customer_contracts');
        Schema::dropIfExists('ap_private_resources');
        Schema::dropIfExists('ap_faqs');
        Schema::dropIfExists('ap_notifications');
        Schema::dropIfExists('ap_services');
        Schema::dropIfExists('ap_customer_users');
        Schema::dropIfExists('ap_profiles');
    }
};
