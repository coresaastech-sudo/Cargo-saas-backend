<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_gl_account_groups AS SELECT * FROM gl_account_groups');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_accounts AS SELECT * FROM gl_accounts');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_charts AS SELECT * FROM gl_charts');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_posting_rules AS SELECT * FROM gl_posting_rules');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_report_columns AS SELECT * FROM gl_report_columns');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_report_configs AS SELECT * FROM gl_report_configs');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_report_runs AS SELECT * FROM gl_report_runs');
        DB::statement('CREATE OR REPLACE VIEW vw_gl_transactions AS SELECT * FROM gl_transactions');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_gl_transactions');
        DB::statement('DROP VIEW IF EXISTS vw_gl_report_runs');
        DB::statement('DROP VIEW IF EXISTS vw_gl_report_configs');
        DB::statement('DROP VIEW IF EXISTS vw_gl_report_columns');
        DB::statement('DROP VIEW IF EXISTS vw_gl_posting_rules');
        DB::statement('DROP VIEW IF EXISTS vw_gl_charts');
        DB::statement('DROP VIEW IF EXISTS vw_gl_accounts');
        DB::statement('DROP VIEW IF EXISTS vw_gl_account_groups');
    }
};
