<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdEodLog extends Model
{
    use HasFactory;
    protected $table = 'ad_eod_log';

    protected $fillable = [
        'id',
        'eoddate',
        'stepno',
        'name',
        'name2',
        'statusid',
        'stepdesc',
        'controller',
        'function',
        'exturl',
        'useexturl',
        'sqlscript',
        'runmonth',
        'runday',
        'startdate',
        'enddate',
        'sendsms',
        'sendemail',
        'orderno',
        'allcount',
        'succount',
        'errcount',
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
        'sendsms' => 'integer',
        'sendemail' => 'integer',
        'enddate' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
