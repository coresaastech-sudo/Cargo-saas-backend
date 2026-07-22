<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstTxnFee extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_txn_fee';

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

    protected $casts = [
        "ACTION_CODE" => 'string',
        "feecode" => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',

    ];
}
