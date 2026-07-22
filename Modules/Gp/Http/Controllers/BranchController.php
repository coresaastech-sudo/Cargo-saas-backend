<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Entities\Branch;
use Modules\Gp\Services\ActionLookupService;

class BranchController extends Controller
{
    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('gp_branches')) {
            return [];
        }

        return $this->getGridData(
            $request,
            Branch::query()->where('status', '<>', 'deleted'),
            ['branch_code' => 'branch_code', 'name' => 'name', 'organization_id' => 'organization_id', 'status' => 'status'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }
}
