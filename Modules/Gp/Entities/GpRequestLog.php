<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpRequestLog extends Model
{
    use HasFactory;

    protected $table = 'gp_request_logs';

    protected $fillable = [
        'id',
        'action_code',
        'method',
        'path',
        'request_body',
        'response_body',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'organization_id',
        'branch_id',
        'status',
        'statusid',
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
