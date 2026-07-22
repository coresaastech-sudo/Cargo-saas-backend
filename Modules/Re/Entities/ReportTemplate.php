<?php

namespace Modules\Re\Entities;

use App\Models\Model;

class ReportTemplate extends Model
{
    protected $table = 're_report_templates';

    protected $fillable = [
        'organization_id',
        'report_key',
        'name',
        'module_code',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];
}
