<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdEodLogDetail extends Model
{
    use HasFactory;

    protected $table = 'ad_eod_log_detail';

    protected $fillable = [
        'id',
        'eoddate',
        'stepno',
        'acntno',
        'acntbrchno',
        'errdesc',
        'ACTION_CODE',
        'errtype',
        'orderno',
        'instid',
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
