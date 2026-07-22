<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdDividentEquityChange extends Model
{
    use HasFactory;

    protected $table = 'ad_divident_equity_change';

    protected $fillable = [
        'startdate',
        'enddate',
        'prodcode',
        'rowno',
        'name',
        'id1',
        'custno',
        'txndate',
        'acntno',
        'startbal',
        'addamount',
        'adddate',
        'minusamount',
        'minusdate',
        'endbal',
        'weight',
        'process_statusid',
        'completed_at',
        'completed_by',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'startdate' => 'date:Y-m-d',
        'enddate' => 'date:Y-m-d',
        'txndate' => 'date:Y-m-d',
        'adddate' => 'date:Y-m-d',
        'minusdate' => 'date:Y-m-d',
        'startbal' => 'float',
        'addamount' => 'float',
        'minusamount' => 'float',
        'endbal' => 'float',
        'weight' => 'float',
        'process_statusid' => 'integer',
        'completed_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];
}
