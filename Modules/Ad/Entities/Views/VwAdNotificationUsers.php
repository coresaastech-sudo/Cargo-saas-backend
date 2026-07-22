<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdNotificationUsers extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_notification_users';

    protected $fillable = [
        'type',
        'type_name',
        'custid',
        'instid',
        'id1',
        'fname',
        'lname',
        'email',
        'phone',
        'statusid',
        'device_token',
        'instname',
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
