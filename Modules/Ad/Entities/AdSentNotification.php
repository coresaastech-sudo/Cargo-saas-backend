<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdSentNotification extends Model
{
    use HasFactory;

    protected $table = 'ad_sent_notification';

    protected $fillable = [
        'id',
        'reciever',
        'title',
        'type',
        'description',
        'body',
        'statusid',
        'instid',
        'error_msg',
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
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
