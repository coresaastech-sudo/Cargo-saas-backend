<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Services\ActionLookupService;
use Modules\Re\Entities\ReportTemplate;

class ReportTemplateController extends Controller
{
    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('re_report_templates')) {
            return [];
        }

        return $this->getGridData(
            $request,
            ReportTemplate::query()->where('status', '<>', 'deleted'),
            ['report_key' => 'report_key', 'name' => 'name', 'module_code' => 'module_code', 'organization_id' => 'organization_id'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }
}
