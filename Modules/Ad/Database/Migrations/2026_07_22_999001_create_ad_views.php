<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_ad_automation_rules AS SELECT * FROM ad_automation_rules');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_automation_runs AS SELECT * FROM ad_automation_runs');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_email_blacklists AS SELECT * FROM ad_email_blacklists');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_notification_templates AS SELECT * FROM ad_notification_templates');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_notifications AS SELECT * FROM ad_notifications');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_operators AS SELECT * FROM ad_operators');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_receipt_configs AS SELECT * FROM ad_receipt_configs');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_receipt_logs AS SELECT * FROM ad_receipt_logs');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_report_permissions AS SELECT * FROM ad_report_permissions');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_secret_policies AS SELECT * FROM ad_secret_policies');
        DB::statement('CREATE OR REPLACE VIEW vw_ad_settlement_accounts AS SELECT * FROM ad_settlement_accounts');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_roles AS SELECT * FROM gp_roles');
        DB::statement('CREATE OR REPLACE VIEW vw_gp_user_roles AS SELECT * FROM gp_user_roles');
        DB::statement('CREATE OR REPLACE VIEW vw_users AS SELECT * FROM users');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_users');
        DB::statement('DROP VIEW IF EXISTS vw_gp_user_roles');
        DB::statement('DROP VIEW IF EXISTS vw_gp_roles');
        DB::statement('DROP VIEW IF EXISTS vw_ad_settlement_accounts');
        DB::statement('DROP VIEW IF EXISTS vw_ad_secret_policies');
        DB::statement('DROP VIEW IF EXISTS vw_ad_report_permissions');
        DB::statement('DROP VIEW IF EXISTS vw_ad_receipt_logs');
        DB::statement('DROP VIEW IF EXISTS vw_ad_receipt_configs');
        DB::statement('DROP VIEW IF EXISTS vw_ad_operators');
        DB::statement('DROP VIEW IF EXISTS vw_ad_notifications');
        DB::statement('DROP VIEW IF EXISTS vw_ad_notification_templates');
        DB::statement('DROP VIEW IF EXISTS vw_ad_email_blacklists');
        DB::statement('DROP VIEW IF EXISTS vw_ad_automation_runs');
        DB::statement('DROP VIEW IF EXISTS vw_ad_automation_rules');
    }
};
