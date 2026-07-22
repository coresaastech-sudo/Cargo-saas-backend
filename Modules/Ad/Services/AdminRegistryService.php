<?php

namespace Modules\Ad\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminRegistryService
{
    public function description(): string
    {
        return 'Admin user, role, operator and permission registry service.';
    }

    public function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    public function list(string $table, array $orders = [['field' => 'id', 'dir' => 'desc']], ?int $organizationId = null): array
    {
        if (! $this->tableReady($table)) {
            return [];
        }

        $query = DB::table($table);

        if ($organizationId && Schema::hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if (Schema::hasColumn($table, 'statusid')) {
            $query->where('statusid', '<>', -1);
        } elseif (Schema::hasColumn($table, 'status')) {
            $query->where('status', '<>', 'deleted');
        }

        foreach ($orders as $order) {
            $query->orderBy($order['field'], $order['dir'] ?? 'asc');
        }

        return $query->get()->toArray();
    }

    public function dashboard(array $tables): array
    {
        return collect($tables)->mapWithKeys(function (string $table): array {
            if (! $this->tableReady($table)) {
                return [$table => ['ready' => false, 'count' => 0]];
            }

            return [$table => ['ready' => true, 'count' => DB::table($table)->count()]];
        })->all();
    }
}
