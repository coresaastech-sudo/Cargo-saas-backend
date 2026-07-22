<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('re_report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('report_key', 80);
            $table->string('name', 200);
            $table->string('module_code', 20)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'report_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_report_templates');
    }
};
