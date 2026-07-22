<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstSusp extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_susp_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "id",
        "acntcode",
        "brchno",
        "curcode",
        "acnttype",
        "acntno",
        "acntdesc",
        "statusid",
        "instid",
        "created_by",
        "updated_by",
        "created_at",
        "updated_at",
        "created_name",
        "updated_name"
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
    ];
}
