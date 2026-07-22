<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlReportRun extends Model
{
    use HasFactory;

    protected $table = 'gl_report_runs';

    protected $fillable = [
        'id',
        'report_config_id',
        'parameters',
        'result_summary',
        'status',
        'error_message',
        'organization_id',
        'branch_id',
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
