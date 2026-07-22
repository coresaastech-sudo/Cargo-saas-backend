<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstUserRole extends Model
{
    use HasFactory;
    protected $table = 'vw_inst_user_role_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "id",
        "rolename",
        "rolename2",
        "startdate",
        "enddate",
        "statusid",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
        "instid",
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'instid' => 'integer',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'enddate' => 'date:Y-m-d',
        'startdate' => 'date:Y-m-d',
    ];
}
