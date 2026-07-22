<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdCgwTxnDescCombination extends Model
{
    use HasFactory;

    protected $table = 'ad_cgw_txn_desc_combination';

    protected $fillable = [
        'id',
        'value',
        'prodcode',
        'name',
        'name2',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'type',
        'acntno',
        'is_income',
        'acnttype'
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
