<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class ActionDefinition extends Model
{
    protected $table = 'gp_action_registry';

    protected $primaryKey = 'action_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'action_code',
        'module_code',
        'name',
        'name2',
        'controller',
        'function',
        'route',
        'action_type',
        'is_menu',
        'requires_auth',
        'requires_permission',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_menu' => 'boolean',
        'requires_auth' => 'boolean',
        'requires_permission' => 'boolean',
        'sort_order' => 'integer',
    ];
}
