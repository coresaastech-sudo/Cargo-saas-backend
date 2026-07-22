<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdDividentProfit extends Model
{
    use HasFactory;

    protected $table = 'ad_divident_profit';

    protected $fillable = [
        'startdate',
        'enddate',
        'prodcode',
        'dividendamount',
        'summary',
        'zeroignore',
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
        'dividendamount' => 'float',
        'summary' => 'integer',
        'zeroignore' => 'integer',
        'process_statusid' => 'integer',
        'completed_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];

    public function details()
    {
        return $this->hasMany(AdDividentProfitDetail::class, 'profit_id');
    }
}
