<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpChangeLog extends Model
{
    use HasFactory;

    protected $table = 'gp_change_logs';

    protected $fillable = [
        'id',
        'entity_type',
        'entity_id',
        'change_type',
        'old_values',
        'new_values',
        'created_by',
        'organization_id',
        'branch_id',
        'status',
        'statusid',
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
