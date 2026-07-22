<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Gp\Services\ActionLookupService;

class RoleAdminController extends Controller
{
    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('gp_roles')) {
            return [];
        }

        return $this->getGridData(
            $request,
            DB::table('gp_roles')->where('status', 'active'),
            ['id' => 'id', 'role_code' => 'role_code', 'name' => 'name', 'organization_id' => 'organization_id'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }
}
