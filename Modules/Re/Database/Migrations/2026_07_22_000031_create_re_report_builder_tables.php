<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_report_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('re_report_templates', 'config')) {
                $table->json('config')->nullable()->after('description');
            }
        });

        Schema::create('re_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('dataset_code', 80);
            $table->string('name', 160);
            $table->string('module_code', 20)->nullable();
            $table->string('source_table')->nullable();
            $table->json('query_config')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_dataset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('re_datasets')->cascadeOnDelete();
            $table->string('field_code', 80);
            $table->string('name', 160);
            $table->string('data_type', 60)->default('string');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_report_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_template_id');
            $table->string('content_type', 60)->nullable();
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_report_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_template_id');
            $table->string('dimension_code', 80);
            $table->string('name', 160);
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_report_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_template_id');
            $table->string('parameter_code', 80);
            $table->string('name', 160);
            $table->string('data_type', 60)->default('string');
            $table->boolean('required')->default(false);
            $table->string('default_value')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_report_parameter_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('parameter_id');
            $table->string('option_code', 80);
            $table->string('name', 160);
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('re_report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_template_id')->nullable();
            $table->string('export_type', 60)->nullable();
            $table->string('file_path')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('re_report_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->unsignedBigInteger('report_template_id')->nullable();
            $table->json('parameters')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_report_run_logs');
        Schema::dropIfExists('re_report_exports');
        Schema::dropIfExists('re_report_parameter_options');
        Schema::dropIfExists('re_report_parameters');
        Schema::dropIfExists('re_report_dimensions');
        Schema::dropIfExists('re_report_contents');
        Schema::dropIfExists('re_dataset_fields');
        Schema::dropIfExists('re_datasets');
    }
};
