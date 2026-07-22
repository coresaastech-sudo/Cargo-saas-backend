<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdNotifications extends Model
{
    use HasFactory;

    protected $table = 'ad_notifications';

    protected $fillable = [
        'id',
        'title',
        'description',
        'is_all_cust',
        'is_all_emp',
        'is_all_meapp_user',
        'notiftype',
        'execfreq',
        'usetemp',
        'reportActionCode',
        'autojobid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'url'
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
