<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('gp_organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('gp_branches')->nullOnDelete();
            $table->string('sale_no', 80);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->string('currency', 3)->default('MNT');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'sale_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
