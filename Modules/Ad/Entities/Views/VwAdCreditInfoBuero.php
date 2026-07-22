<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdCreditInfoBuero extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_credit_info_buero';

    protected $fillable = [
        'id',
        'custid',
        'datapackageno',
        'request',
        'response',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        // 'updated_at' => 'datetime:Y-m-d H:i:s',
        // 'created_at' => 'datetime:Y-m-d H:i:s',
    ];
}
