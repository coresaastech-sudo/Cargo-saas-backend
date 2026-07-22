<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdBatchTxnDetail extends Model
{
    use HasFactory;

    protected $table = 'ad_batch_txn_detail';

    protected $fillable = [
        'batchid',
        'acntno',
        'txndate',
        'txncode',
        'description',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'jrno',
        'txndesc',
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
