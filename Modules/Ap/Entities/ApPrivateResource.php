<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApPrivateResource extends Model
{
    use HasFactory;

    protected $table = 'ap_private_resources';

    protected $fillable = [
        'id',
        'resource_code',
        'name',
        'resource_type',
        'path',
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
