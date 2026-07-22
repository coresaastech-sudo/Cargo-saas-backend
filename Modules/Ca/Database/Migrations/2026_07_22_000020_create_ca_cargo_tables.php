<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ca_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('gp_organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('gp_branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('cr_customers')->nullOnDelete();
            $table->string('tracking_no', 80);
            $table->string('origin', 120)->nullable();
            $table->string('destination', 120)->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('gross_weight', 14, 3)->default(0);
            $table->decimal('chargeable_weight', 14, 3)->default(0);
            $table->string('shipment_status', 30)->default('draft');
            $table->string('payment_status', 30)->default('unpaid');
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->string('currency', 3)->default('MNT');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'tracking_no']);
            $table->index(['organization_id', 'shipment_status']);
        });

        Schema::create('ca_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('ca_shipments')->cascadeOnDelete();
            $table->string('event_code', 60);
            $table->string('event_name');
            $table->timestamp('occurred_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ca_shipment_events');
        Schema::dropIfExists('ca_shipments');
    }
};
