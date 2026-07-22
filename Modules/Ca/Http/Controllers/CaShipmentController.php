<?php

namespace Modules\Ca\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Ca\Entities\Shipment;
use Modules\Gp\Services\ActionLookupService;

class CaShipmentController extends Controller
{
    public function dashboard(Request $request, ActionLookupService $lookup): array
    {
        if (! $lookup->hasTable('ca_shipments')) {
            return ['shipments_total' => 0, 'shipments_in_transit' => 0, 'shipments_delivered' => 0];
        }

        $base = DB::table('ca_shipments')
            ->where('status', '<>', 'deleted')
            ->when($request->user()?->organization_id, fn ($query) => $query->where('organization_id', $request->user()->organization_id));

        return [
            'shipments_total' => (clone $base)->count(),
            'shipments_in_transit' => (clone $base)->where('shipment_status', 'in_transit')->count(),
            'shipments_delivered' => (clone $base)->where('shipment_status', 'delivered')->count(),
        ];
    }

    public function index(Request $request, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('ca_shipments')) {
            return [];
        }

        return $this->getGridData(
            $request,
            Shipment::query()->where('status', '<>', 'deleted'),
            ['tracking_no' => 'tracking_no', 'shipment_status' => 'shipment_status', 'payment_status' => 'payment_status', 'origin' => 'origin', 'destination' => 'destination'],
            [['field' => 'tracking_no', 'dir' => 'desc']]
        );
    }
}
