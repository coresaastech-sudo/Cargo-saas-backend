<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpUserDelegate extends Model
{
    use HasFactory;

    protected $table = 'gp_user_delegates';

    protected $fillable = [
        'id',
        'user_id',
        'delegate_user_id',
        'start_at',
        'end_at',
        'reason',
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
