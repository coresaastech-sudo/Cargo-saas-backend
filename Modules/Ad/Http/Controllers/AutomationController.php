<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ad\Services\AutomationService;
use Modules\Gp\Services\ActionLookupService;

class AutomationController extends Controller
{
    public function index(Request $request, AutomationService $automation, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('ad_automation_rules')) {
            return [];
        }

        return $automation->list($request->user()?->organization_id);
    }
}
