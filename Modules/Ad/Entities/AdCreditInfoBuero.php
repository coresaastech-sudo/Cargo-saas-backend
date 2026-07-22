<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdCreditInfoBuero extends Model
{
    use HasFactory;

    protected $table = 'ad_credit_info_buero';

    protected $fillable = [
        'id',
        'custno',
        'acntno',
        'datapackageno',
        'type',
        'request',
        'response',
        'totalnum',
        'successnum',
        'statusid',
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
        'updated_at' => 'datetime:Y-m-d',
        'created_at' => 'datetime:Y-m-d',
    ];
}
