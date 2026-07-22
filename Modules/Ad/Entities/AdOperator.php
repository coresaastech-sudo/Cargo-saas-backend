<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdOperator extends Model
{
    use HasFactory;

    protected $table = 'ad_operators';

    protected $fillable = [
        'id',
        'operator_code',
        'name',
        'user_id',
        'branch_id',
        'settings',
        'status',
        'organization_id',
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
