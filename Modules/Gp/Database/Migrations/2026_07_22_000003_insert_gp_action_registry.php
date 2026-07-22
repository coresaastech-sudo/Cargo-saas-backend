<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Gp\Support\ActionCatalog;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (ActionCatalog::modules() as $module) {
            DB::table('gp_modules')->updateOrInsert(
                ['module_code' => $module['module_code']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'] ?? null,
                    'sort_order' => $module['sort_order'] ?? 0,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach (ActionCatalog::actions() as $action) {
            DB::table('gp_action_registry')->updateOrInsert(
                ['action_code' => $action['action_code']],
                [
                    'module_code' => $action['module_code'],
                    'group_code' => $action['group_code'] ?? null,
                    'group_name' => $action['group_name'] ?? null,
                    'icon' => $action['icon'] ?? null,
                    'name' => $action['name'],
                    'name2' => $action['name2'] ?? null,
                    'controller' => $action['controller'],
                    'function' => $action['function'],
                    'route' => $action['route'] ?? null,
                    'action_type' => $action['action_type'] ?? 'backoffice',
                    'is_menu' => $action['is_menu'] ?? false,
                    'requires_auth' => $action['requires_auth'] ?? true,
                    'requires_permission' => $action['requires_permission'] ?? true,
                    'sort_order' => $action['sort_order'] ?? 0,
                    'status' => 'active',
                    'created_by' => 1,
                    'updated_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('gp_action_registry')->whereIn('action_code', collect(ActionCatalog::actions())->pluck('action_code')->all())->delete();
        DB::table('gp_modules')->whereIn('module_code', collect(ActionCatalog::modules())->pluck('module_code')->all())->delete();
    }
};
