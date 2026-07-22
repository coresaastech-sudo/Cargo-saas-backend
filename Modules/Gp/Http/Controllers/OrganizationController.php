<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Entities\Organization;
use Modules\Gp\Services\ActionLookupService;
use Modules\Gp\Services\OrganizationService;

class OrganizationController extends Controller
{
    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('gp_organizations')) {
            return [];
        }

        return $this->getGridData(
            $request,
            Organization::query()->where('status', '<>', 'deleted'),
            ['organization_code' => 'organization_code', 'name' => 'name', 'email' => 'email', 'status' => 'status'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }

    public function store(Request $request, OrganizationService $organizations): array
    {
        $validated = $this->validateMe($request, [
            'organization_code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        return ['id' => $organizations->create(array_merge($validated, ['status' => 'active']))];
    }

    public function update(Request $request, OrganizationService $organizations): array
    {
        $validated = $this->validateMe($request, [
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $id = (int) $validated['id'];
        unset($validated['id']);
        $organizations->update($id, $validated);

        return ['id' => $id];
    }
}
