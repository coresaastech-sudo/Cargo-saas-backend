<?php

namespace Modules\Ad\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdCreditInfoBueroHist extends Model
{
    use HasFactory;

    protected $table = 'ad_credit_info_buero_hist';

    protected $fillable = [
        'id',
        'lastexecuteddate',
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
    // protected $casts = [
    //     'updated_at' => 'date:Y-m-d H:i:s',
    //     'created_at' => 'date:Y-m-d H:i:s',
    // ];
}
