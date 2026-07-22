<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApAccessToken extends Model
{
    use HasFactory;

    protected $table = 'ap_access_tokens';

    protected $fillable = [
        'id',
        'customer_id',
        'token_name',
        'abilities',
        'last_used_at',
        'expires_at',
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
