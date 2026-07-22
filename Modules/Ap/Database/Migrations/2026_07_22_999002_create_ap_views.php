<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_ap_access_tokens AS SELECT * FROM ap_access_tokens');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_customer_contracts AS SELECT * FROM ap_customer_contracts');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_customer_users AS SELECT * FROM ap_customer_users');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_faqs AS SELECT * FROM ap_faqs');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_notifications AS SELECT * FROM ap_notifications');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_private_resources AS SELECT * FROM ap_private_resources');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_profiles AS SELECT * FROM ap_profiles');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_services AS SELECT * FROM ap_services');
        DB::statement('CREATE OR REPLACE VIEW vw_ap_stop_services AS SELECT * FROM ap_stop_services');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_ap_stop_services');
        DB::statement('DROP VIEW IF EXISTS vw_ap_services');
        DB::statement('DROP VIEW IF EXISTS vw_ap_profiles');
        DB::statement('DROP VIEW IF EXISTS vw_ap_private_resources');
        DB::statement('DROP VIEW IF EXISTS vw_ap_notifications');
        DB::statement('DROP VIEW IF EXISTS vw_ap_faqs');
        DB::statement('DROP VIEW IF EXISTS vw_ap_customer_users');
        DB::statement('DROP VIEW IF EXISTS vw_ap_customer_contracts');
        DB::statement('DROP VIEW IF EXISTS vw_ap_access_tokens');
    }
};
