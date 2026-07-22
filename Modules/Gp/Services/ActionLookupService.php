<?php

namespace Modules\Gp\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Gp\Entities\ActionDefinition;
use Modules\Gp\Support\ActionCatalog;
use Throwable;

class ActionLookupService
{
    public function find(string $actionCode): ?object
    {
        if ($this->hasTable('gp_action_registry')) {
            $action = ActionDefinition::where('action_code', $actionCode)
                ->where('status', 'active')
                ->first();

            if ($action) {
                return (object) $action->toArray();
            }
        }

        $fallback = collect(ActionCatalog::actions())->firstWhere('action_code', $actionCode);

        return $fallback ? (object) array_merge([
            'requires_auth' => true,
            'requires_permission' => true,
            'is_menu' => false,
            'status' => 'active',
        ], $fallback) : null;
    }

    public function all(): array
    {
        if ($this->hasTable('gp_action_registry')) {
            return ActionDefinition::where('status', 'active')
                ->orderBy('module_code')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ActionDefinition $action): array => $action->toArray())
                ->all();
        }

        return ActionCatalog::actions();
    }

    public function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
