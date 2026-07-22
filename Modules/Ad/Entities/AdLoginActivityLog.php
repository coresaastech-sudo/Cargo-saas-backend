<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdLoginActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $table = "ad_login_activity_log";

    public $fillable = [
        'userid',
        'device_ip',
        'created_at',
        'last_login_date',
        'agent',
        'channel',
        'deviceid',
        'devicename',
        'statusid',
        'created_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'userid' => 'integer',
        'device_ip' => 'string',
        'created_at' => 'date:Y-m-d H:i:s',
        'last_login_date' => 'date:Y-m-d H:i:s',
        'agent' => 'string',
    ];
}
