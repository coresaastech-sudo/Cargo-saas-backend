<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpProviderConfig extends Model
{
    use HasFactory;

    protected $table = 'gp_provider_configs';

    protected $fillable = [
        'id',
        'provider_code',
        'name',
        'provider_type',
        'endpoint',
        'credentials',
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
