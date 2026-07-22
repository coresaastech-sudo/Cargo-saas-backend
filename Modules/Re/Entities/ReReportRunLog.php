<?php

namespace Modules\Re\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReReportRunLog extends Model
{
    use HasFactory;

    protected $table = 're_report_run_logs';

    protected $fillable = [
        'id',
        'report_template_id',
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
