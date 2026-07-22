<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_gp_audit_logs AS SELECT * FROM gp_audit_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_change_logs AS SELECT * FROM gp_change_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_email_logs AS SELECT * FROM gp_email_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_error_logs AS SELECT * FROM gp_error_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_failed_job_logs AS SELECT * FROM gp_failed_job_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_file_assets AS SELECT * FROM gp_file_assets');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_mail_configs AS SELECT * FROM gp_mail_configs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_modules AS SELECT * FROM gp_modules');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_photo_assets AS SELECT * FROM gp_photo_assets');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_provider_configs AS SELECT * FROM gp_provider_configs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_request_logs AS SELECT * FROM gp_request_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_response_codes AS SELECT * FROM gp_response_codes');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_role_actions AS SELECT * FROM gp_role_actions');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_sequences AS SELECT * FROM gp_sequences');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_service_fees AS SELECT * FROM gp_service_fees');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_service_tariffs AS SELECT * FROM gp_service_tariffs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_service_types AS SELECT * FROM gp_service_types');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_suspensions AS SELECT * FROM gp_suspensions');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_system_jobs AS SELECT * FROM gp_system_jobs');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_user_delegates AS SELECT * FROM gp_user_delegates');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_whitelabel_configs AS SELECT * FROM gp_whitelabel_configs');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_gp_whitelabel_configs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_user_delegates');
        DB::statement('DROP VIEW IF EXISTS vw_gp_system_jobs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_suspensions');
        DB::statement('DROP VIEW IF EXISTS vw_gp_service_types');
        DB::statement('DROP VIEW IF EXISTS vw_gp_service_tariffs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_service_fees');
        DB::statement('DROP VIEW IF EXISTS vw_gp_sequences');
        DB::statement('DROP VIEW IF EXISTS vw_gp_role_actions');
        DB::statement('DROP VIEW IF EXISTS vw_gp_response_codes');
        DB::statement('DROP VIEW IF EXISTS vw_gp_request_logs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_provider_configs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_photo_assets');
        DB::statement('DROP VIEW IF EXISTS vw_gp_modules');
        DB::statement('DROP VIEW IF EXISTS vw_gp_mail_configs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_file_assets');
        DB::statement('DROP VIEW IF EXISTS vw_gp_failed_job_logs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_error_logs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_email_logs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_change_logs');
        DB::statement('DROP VIEW IF EXISTS vw_gp_audit_logs');
    }
};
