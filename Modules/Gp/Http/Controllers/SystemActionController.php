<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Entities\ActionDefinition;
use Modules\Gp\Services\ActionLookupService;

class SystemActionController extends Controller
{
    public function index(Request $request, ActionLookupService $actions)
    {
        if (! $actions->hasTable('gp_action_registry')) {
            return $actions->all();
        }

        return $this->getGridData(
            $request,
            ActionDefinition::query(),
            [
                'action_code' => 'action_code',
                'name' => 'name',
                'module_code' => 'module_code',
                'route' => 'route',
                'status' => 'status',
            ],
            [['field' => 'action_code', 'dir' => 'asc']]
        );
    }
}
