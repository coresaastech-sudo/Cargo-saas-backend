<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class GpAuditLog extends Model
{
    protected $table = 'GP_audit_log';

    protected $fillable = [
        'userid',
        'instid',
        'ip',
        'AC',
        'parent_objectid',
        'objectid',
        'object_type',
        'action_type',
    ];
}
