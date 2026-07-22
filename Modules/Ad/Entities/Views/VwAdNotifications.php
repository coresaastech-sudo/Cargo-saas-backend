<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdNotifications extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_notifications';

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
        'created_name',
        'temp_name',
        'autojob_name',
        'url',
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
