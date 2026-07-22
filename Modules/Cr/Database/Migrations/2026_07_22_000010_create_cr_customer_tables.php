<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cr_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('gp_organizations')->cascadeOnDelete();
            $table->string('customer_code', 60);
            $table->string('customer_type', 30)->default('person');
            $table->string('name', 200);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('register_no', 60)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'customer_code']);
        });

        Schema::create('cr_customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('cr_customers')->cascadeOnDelete();
            $table->string('contact_type', 30);
            $table->string('value');
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cr_customer_contacts');
        Schema::dropIfExists('cr_customers');
    }
};
