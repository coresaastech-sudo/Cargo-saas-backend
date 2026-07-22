<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_re_dataset_fields AS SELECT * FROM re_dataset_fields');
        DB::statement('CREATE OR REPLACE VIEW vw_re_datasets AS SELECT * FROM re_datasets');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_contents AS SELECT * FROM re_report_contents');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_dimensions AS SELECT * FROM re_report_dimensions');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_exports AS SELECT * FROM re_report_exports');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_parameter_options AS SELECT * FROM re_report_parameter_options');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_parameters AS SELECT * FROM re_report_parameters');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_run_logs AS SELECT * FROM re_report_run_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_re_report_templates AS SELECT * FROM re_report_templates');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_re_report_templates');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_run_logs');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_parameters');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_parameter_options');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_exports');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_dimensions');
        DB::statement('DROP VIEW IF EXISTS vw_re_report_contents');
        DB::statement('DROP VIEW IF EXISTS vw_re_datasets');
        DB::statement('DROP VIEW IF EXISTS vw_re_dataset_fields');
    }
};
