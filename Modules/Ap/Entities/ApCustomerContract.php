<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApCustomerContract extends Model
{
    use HasFactory;

    protected $table = 'ap_customer_contracts';

    protected $fillable = [
        'id',
        'contract_no',
        'customer_id',
        'contract_type',
        'start_date',
        'end_date',
        'terms',
        'status',
        'organization_id',
        'branch_id',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
