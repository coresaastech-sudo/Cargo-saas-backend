<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdAutomationRule extends Model
{
    use HasFactory;

    protected $table = 'ad_automation_rules';

    protected $fillable = [
        'id',
        'rule_code',
        'name',
        'trigger_event',
        'conditions',
        'actions',
        'handler',
        'schedule',
        'settings',
        'status',
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
