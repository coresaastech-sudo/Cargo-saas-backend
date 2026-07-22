<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class AdHide extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ad_hide';

    protected $fillable = [
        'id',
        'modulekey',
        'module',
        'valuetype',
        'userid',
        'brchno',
        'roleid',
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
