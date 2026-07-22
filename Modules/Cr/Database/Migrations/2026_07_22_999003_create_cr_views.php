<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_addresses AS SELECT * FROM cr_customer_addresses');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_batches AS SELECT * FROM cr_customer_batches');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_billing_accounts AS SELECT * FROM cr_customer_billing_accounts');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_contacts AS SELECT * FROM cr_customer_contacts');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_credentials AS SELECT * FROM cr_customer_credentials');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_delivery_preferences AS SELECT * FROM cr_customer_delivery_preferences');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_documents AS SELECT * FROM cr_customer_documents');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_messages AS SELECT * FROM cr_customer_messages');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_relationships AS SELECT * FROM cr_customer_relationships');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customer_stakeholders AS SELECT * FROM cr_customer_stakeholders');
        DB::statement('CREATE OR REPLACE VIEW vw_cr_customers AS SELECT * FROM cr_customers');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_cr_customers');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_stakeholders');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_relationships');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_messages');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_documents');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_delivery_preferences');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_credentials');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_contacts');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_billing_accounts');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_batches');
        DB::statement('DROP VIEW IF EXISTS vw_cr_customer_addresses');
    }
};
