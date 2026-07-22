<?php

namespace Modules\Gp\Services;

use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function create(array $data): int
    {
        return DB::table('gp_organizations')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function update(int $id, array $data): void
    {
        DB::table('gp_organizations')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));
    }
}
