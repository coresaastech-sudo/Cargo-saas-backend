<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstUserRole extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_user_roles';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'userid';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
            "id",
            "instid",
            "userid",
            "roleid",
            "startdate",
            "enddate",
            "statusid",
            "created_by",
            "updated_by",
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'instid' => 'integer',
        'startdate' => 'date:Y-m-d',
        'enddate' => 'date:Y-m-d',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
