<?php

namespace Modules\Gp\Services;

use Illuminate\Support\Facades\DB;
use Modules\Gp\Support\ActionCatalog;

class NavigationService
{
    public function __construct(private readonly ActionLookupService $actions) {}

    public function build(): array
    {
        $modules = $this->modules();
        $actions = collect($this->actions->all());

        return collect($modules)
            ->map(function (array $module) use ($actions): array {
                $children = $actions
                    ->filter(fn (array $action): bool => ($action['module_code'] ?? null) === $module['module_code'] && (bool) ($action['is_menu'] ?? false))
                    ->sortBy('sort_order')
                    ->map(fn (array $action): array => [
                        'label' => $action['name'],
                        'action' => $action['action_code'],
                        'route' => $action['route'] ?? null,
                        'module_code' => $action['module_code'],
                        'group_code' => $action['group_code'] ?? null,
                        'group_name' => $action['group_name'] ?? null,
                    ])
                    ->values()
                    ->all();

                return [
                    'module_code' => $module['module_code'],
                    'label' => $module['name'],
                    'sort_order' => $module['sort_order'] ?? 0,
                    'children' => $children,
                ];
            })
            ->filter(fn (array $module): bool => count($module['children']) > 0)
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function modules(): array
    {
        if ($this->actions->hasTable('gp_modules')) {
            return DB::table('gp_modules')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($module): array => (array) $module)
                ->all();
        }

        return ActionCatalog::modules();
    }
}
