<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('organization_code', 40)->unique();
            $table->string('name', 200);
            $table->string('name2', 200)->nullable();
            $table->string('register_no', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gp_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('gp_organizations')->cascadeOnDelete();
            $table->string('branch_code', 40);
            $table->string('name', 200);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'branch_code']);
        });

        Schema::create('gp_modules', function (Blueprint $table) {
            $table->string('module_code', 20)->primary();
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('gp_action_registry', function (Blueprint $table) {
            $table->string('action_code', 80)->primary();
            $table->string('module_code', 20)->index();
            $table->string('name', 250);
            $table->string('name2', 250)->nullable();
            $table->string('controller', 250);
            $table->string('function', 120);
            $table->string('route')->nullable();
            $table->string('action_type', 30)->default('backoffice');
            $table->boolean('is_menu')->default(false);
            $table->boolean('requires_auth')->default(true);
            $table->boolean('requires_permission')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gp_org_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('gp_organizations')->cascadeOnDelete();
            $table->string('action_code', 80);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'action_code']);
        });

        Schema::create('gp_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('role_code', 60);
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'role_code']);
        });

        Schema::create('gp_role_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('gp_roles')->cascadeOnDelete();
            $table->string('action_code', 80);
            $table->timestamps();
            $table->unique(['role_id', 'action_code']);
        });

        Schema::create('gp_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('gp_roles')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'role_id']);
        });

        Schema::create('gp_dictionaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('dictionary_code', 60);
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'dictionary_code']);
        });

        Schema::create('gp_dictionary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dictionary_id')->constrained('gp_dictionaries')->cascadeOnDelete();
            $table->string('item_code', 80);
            $table->string('name', 120);
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['dictionary_id', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_dictionary_items');
        Schema::dropIfExists('gp_dictionaries');
        Schema::dropIfExists('gp_user_roles');
        Schema::dropIfExists('gp_role_actions');
        Schema::dropIfExists('gp_roles');
        Schema::dropIfExists('gp_org_actions');
        Schema::dropIfExists('gp_action_registry');
        Schema::dropIfExists('gp_modules');
        Schema::dropIfExists('gp_branches');
        Schema::dropIfExists('gp_organizations');
    }
};
