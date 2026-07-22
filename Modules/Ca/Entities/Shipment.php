<?php

namespace Modules\Ca\Entities;

use App\Models\Model;

class Shipment extends Model
{
    protected $table = 'ca_shipments';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'customer_id',
        'tracking_no',
        'origin',
        'destination',
        'package_count',
        'gross_weight',
        'chargeable_weight',
        'shipment_status',
        'payment_status',
        'total_amount',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'package_count' => 'integer',
        'gross_weight' => 'decimal:3',
        'chargeable_weight' => 'decimal:3',
        'total_amount' => 'decimal:2',
    ];
}
