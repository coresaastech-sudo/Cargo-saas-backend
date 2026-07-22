<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('rule_code', 80);
            $table->string('name', 200);
            $table->string('trigger_event', 120);
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'rule_code']);
        });

        Schema::create('ad_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('template_code', 80);
            $table->string('channel', 30)->default('in_app');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'template_code', 'channel']);
        });

        Schema::create('ad_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30)->default('in_app');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_notifications');
        Schema::dropIfExists('ad_notification_templates');
        Schema::dropIfExists('ad_automation_rules');
    }
};
