<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpLogErrorList extends Model
{
    use HasFactory;

    protected $table = 'log_errors';

    protected $fillable = [
        'message',
        'context',
        'loglevel',
        'level_name',
        'channel',
        'record_datetime',
        'extra',
        'formatted',
        'remote_addr',
        'user_agent',
        'created_at',
        'created_by',
        'request_uri',
        'request_method',
        'request_content',
        'instid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];
}
