<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwGpInstTxnFeeList extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_txn_fee_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'ACTION_CODE',
        'feecode',
        'deductcracnt',
        'deductdracnt',
        'feecalcamount',
        'rtypecode',
        'whenapply',
        'formula',
        'deductlnrepayacnt',
        'debittxnamount',
        'isbatchfee',

        'instid',
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
        "ACTION_CODE" => 'string',
        "feecode" => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
