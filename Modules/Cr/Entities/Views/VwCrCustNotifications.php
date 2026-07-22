<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustNotifications extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_cust_notifications';

    protected $fillable = [
        'id',
        'custid',
        'notification_id',
        'is_read',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'name',
        'lname',
        'id1',
        'title',
        'description',
        'channel',
        'custtype',
        'url',
        'notiftype',
        'notifstatusid',
        'notifinstid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
}
