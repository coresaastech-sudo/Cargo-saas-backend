<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cr_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('cr_customers', 'customer_type')) {
                $table->string('customer_type', 40)->default('organization')->after('email');
                $table->json('metadata')->nullable()->after('status');
            }
        });

        Schema::table('cr_customer_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('cr_customer_contacts', 'contact_type')) {
                $table->string('contact_type', 60)->nullable()->after('customer_id');
                $table->string('value')->nullable()->after('contact_type');
                $table->boolean('is_primary')->default(false)->after('value');
            }
        });

        Schema::create('cr_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('address_type', 60)->default('primary');
            $table->string('country', 80)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('document_type', 80)->nullable();
            $table->string('document_no', 120)->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('channel', 60)->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('related_customer_id')->nullable();
            $table->string('relation_type', 80)->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('credential_type', 80)->nullable();
            $table->string('credential_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('name', 160);
            $table->string('role', 80)->nullable();
            $table->string('register_no', 80)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('account_code', 80);
            $table->string('account_type', 80)->nullable();
            $table->string('currency', 3)->default('MNT');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_delivery_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('preference_code', 80);
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('cr_customer_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('batch_code', 80);
            $table->string('import_type', 80)->nullable();
            $table->string('file_path')->nullable();
            $table->json('summary')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cr_customer_batches');
        Schema::dropIfExists('cr_customer_delivery_preferences');
        Schema::dropIfExists('cr_customer_billing_accounts');
        Schema::dropIfExists('cr_customer_stakeholders');
        Schema::dropIfExists('cr_customer_credentials');
        Schema::dropIfExists('cr_customer_relationships');
        Schema::dropIfExists('cr_customer_messages');
        Schema::dropIfExists('cr_customer_documents');
        Schema::dropIfExists('cr_customer_addresses');
    }
};
