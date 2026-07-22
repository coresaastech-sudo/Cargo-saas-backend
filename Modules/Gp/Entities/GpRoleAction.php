<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpRoleAction extends Model
{
    use HasFactory;

    protected $table = 'gp_role_actions';

    protected $fillable = [
        'id',
        'role_id',
        'action_code',
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
