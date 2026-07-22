<?php

namespace Modules\Ad\Services;

use Illuminate\Support\Facades\DB;

class AutomationService
{
    public function list(?int $organizationId)
    {
        return DB::table('ad_automation_rules')
            ->when($organizationId, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $organizationId)))
            ->where('status', '<>', 'deleted')
            ->orderBy('name')
            ->get();
    }
}
