<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class GpAuditLogDetail extends Model
{
    protected $table = 'GP_audit_log_detail';

    protected $fillable = [
        'audit_logid',
        'fieldname',
        'new_val',
        'old_val'
    ];
}
