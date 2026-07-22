<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpServiceType extends Model
{
    use HasFactory;

    protected $table = 'gp_service_types';

    protected $fillable = [
        'id',
        'service_type_code',
        'name',
        'description',
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
