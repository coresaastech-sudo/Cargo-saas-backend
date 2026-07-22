<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdRole extends Model
{
    use HasFactory;

    protected $table = 'gp_roles';

    protected $fillable = [
        'id',
        'organization_id',
        'role_code',
        'name',
        'description',
        'is_admin',
        'status',
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
