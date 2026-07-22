<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpMailConfig extends Model
{
    use HasFactory;

    protected $table = 'gp_mail_configs';

    protected $fillable = [
        'id',
        'mail_code',
        'name',
        'driver',
        'from_address',
        'from_name',
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
