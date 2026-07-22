<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrCustomerStakeholder extends Model
{
    use HasFactory;

    protected $table = 'cr_customer_stakeholders';

    protected $fillable = [
        'id',
        'customer_id',
        'name',
        'role',
        'register_no',
        'phone',
        'email',
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
