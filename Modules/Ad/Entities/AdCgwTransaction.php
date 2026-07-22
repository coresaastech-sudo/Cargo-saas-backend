<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdCgwTransaction extends Model
{
    use HasFactory;
    protected $table = 'ad_cgw_transaction';

    protected $fillable = [
        'id',
        'jrno',
        'from_account',
        'amount',
        'curcode',
        'description',
        'to_bank',
        'to_account',
        'to_account_name',
        'transferid',
        'system_date',
        'uuid',
        'source',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'double',
        'system_date' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
