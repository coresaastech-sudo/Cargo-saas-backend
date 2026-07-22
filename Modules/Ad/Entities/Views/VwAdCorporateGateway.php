<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdCorporateGateway extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_corporate_gateway';

    protected $fillable = [
        'id',
        'bank',
        'txnamount',
        'sign',
        'balance',
        'curcode',
        'txndesc',
        'acntno',
        'acnttype',
        'custno',
        'txndate',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'cust_name'
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'txnamount' => 'float',
        'balance' => 'float',
    ];
}
