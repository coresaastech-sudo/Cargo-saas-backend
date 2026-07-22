<?php

namespace Modules\Gp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Gp\Support\ActionCatalog;

class GpDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('gp_action_registry')) {
            return;
        }

        $now = now();
        $this->seedOrganization($now);
        $this->seedActionRegistry($now);
        $this->seedDictionaries($now);
        $this->seedPlatformRole($now);
    }

    private function seedOrganization($now): void
    {
        DB::table('gp_organizations')->updateOrInsert(
            ['organization_code' => 'platform'],
            ['name' => 'Cargo SaaS Platform', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
        );

        $organizationId = DB::table('gp_organizations')->where('organization_code', 'platform')->value('id');

        DB::table('gp_branches')->updateOrInsert(
            ['organization_id' => $organizationId, 'branch_code' => 'main'],
            ['name' => 'Main branch', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function seedActionRegistry($now): void
    {
        foreach (ActionCatalog::modules() as $module) {
            DB::table('gp_modules')->updateOrInsert(
                ['module_code' => $module['module_code']],
                ['name' => $module['name'], 'sort_order' => $module['sort_order'] ?? 0, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (ActionCatalog::actions() as $action) {
            DB::table('gp_action_registry')->updateOrInsert(
                ['action_code' => $action['action_code']],
                [
                    'module_code' => $action['module_code'],
                    'name' => $action['name'],
                    'controller' => $action['controller'],
                    'function' => $action['function'],
                    'route' => $action['route'] ?? null,
                    'is_menu' => $action['is_menu'] ?? false,
                    'requires_auth' => $action['requires_auth'] ?? true,
                    'requires_permission' => $action['requires_permission'] ?? true,
                    'sort_order' => $action['sort_order'] ?? 0,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedDictionaries($now): void
    {
        $dictionaries = [
            'cargo_status' => ['Draft' => 'draft', 'In transit' => 'in_transit', 'Delivered' => 'delivered', 'Cancelled' => 'cancelled'],
            'payment_status' => ['Unpaid' => 'unpaid', 'Paid' => 'paid'],
        ];

        foreach ($dictionaries as $code => $items) {
            DB::table('gp_dictionaries')->updateOrInsert(
                ['organization_id' => null, 'dictionary_code' => $code],
                ['name' => str_replace('_', ' ', $code), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
            );

            $dictionaryId = DB::table('gp_dictionaries')->whereNull('organization_id')->where('dictionary_code', $code)->value('id');
            $sortOrder = 10;

            foreach ($items as $name => $itemCode) {
                DB::table('gp_dictionary_items')->updateOrInsert(
                    ['dictionary_id' => $dictionaryId, 'item_code' => $itemCode],
                    ['name' => $name, 'sort_order' => $sortOrder, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
                );

                $sortOrder += 10;
            }
        }
    }

    private function seedPlatformRole($now): void
    {
        DB::table('gp_roles')->updateOrInsert(
            ['organization_id' => null, 'role_code' => 'platform_admin'],
            ['name' => 'Platform admin', 'is_admin' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]
        );

        $roleId = DB::table('gp_roles')->whereNull('organization_id')->where('role_code', 'platform_admin')->value('id');

        foreach (ActionCatalog::actions() as $action) {
            DB::table('gp_role_actions')->updateOrInsert(
                ['role_id' => $roleId, 'action_code' => $action['action_code']],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
