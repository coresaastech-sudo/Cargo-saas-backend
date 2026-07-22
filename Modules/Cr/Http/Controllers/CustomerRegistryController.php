<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Cr\Entities\Customer;
use Modules\Gp\Services\ActionLookupService;

class CustomerRegistryController extends Controller
{
    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('cr_customers')) {
            return [];
        }

        return $this->getGridData(
            $request,
            Customer::query()->where('status', '<>', 'deleted'),
            ['customer_code' => 'customer_code', 'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'organization_id' => 'organization_id'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }
}
