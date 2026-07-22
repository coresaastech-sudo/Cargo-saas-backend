<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdSettlementAccount extends Model
{
    use HasFactory;

    protected $table = 'ad_settlement_accounts';

    protected $fillable = [
        'id',
        'account_code',
        'name',
        'account_type',
        'account_no',
        'currency',
        'settings',
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
