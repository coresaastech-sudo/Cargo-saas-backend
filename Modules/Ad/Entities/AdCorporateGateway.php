<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

class AdCorporateGateway extends Model
{
    use HasFactory;
    protected $table = 'ad_corporate_gateway';

    protected $fillable = [
        'id',
        'bankcode',
        'banktxndate',
        'bankjrno',
        'bankacntno',
        'bankfromacntno',
        'txnamount',
        'sign',
        'balance',
        'curcode',
        'txndesc',
        'acntno',
        'txn_jrno',
        'txndate',
        'statusid',
        'instid',
        'reason',
        'created_by',
        'updated_by',
        'created_at',
        'source',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'txnamount' => 'double',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
