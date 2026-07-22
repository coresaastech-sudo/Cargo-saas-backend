<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdDividentProfitDetail extends Model
{
    use HasFactory;

    protected $table = 'ad_divident_profit_detail';

    protected $fillable = [
        'profit_id',
        'rowno',
        'no',
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
        'calc_balance',
        'days',
        'day_amount',
        'day_weight',
        'div_amount',
        'div_percent',
        'rate',
        'dividend',
        'taxamount',
        'netamount',
        'recievemethod',
        'recieve_acntno',
        'bank_acntno',
        'process_statusid',
        'completed_at',
        'completed_by',
        'jrno',
    ];

    protected $casts = [
        'txndate' => 'date:Y-m-d',
        'adddate' => 'date:Y-m-d',
        'minusdate' => 'date:Y-m-d',
        'startbal' => 'float',
        'addamount' => 'float',
        'minusamount' => 'float',
        'endbal' => 'float',
        'weight' => 'float',
        'calc_balance' => 'float',
        'days' => 'float',
        'day_amount' => 'float',
        'day_weight' => 'float',
        'div_amount' => 'float',
        'div_percent' => 'float',
        'rate' => 'float',
        'dividend' => 'float',
        'taxamount' => 'float',
        'netamount' => 'float',
        'process_statusid' => 'integer',
        'completed_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];

    public function profit()
    {
        return $this->belongsTo(AdDividentProfit::class, 'profit_id');
    }
}
