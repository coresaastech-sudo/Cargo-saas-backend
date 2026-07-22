<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class AdPermReport extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ad_perm_report';

    protected $fillable = [
        'id',
        'AC',
        'valuetype',
        'userid',
        'brchno',
        'roleid',
        'showbrchno',
        'statusid',
        'instid',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
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
