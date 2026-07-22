<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpUserActList extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_user_act_list';

    protected $fillable = [
        'id',
        'instid',
        'instname',
        'userid',
        'username',
        'act_instid',
        'act_instname',
        'act_userid',
        'act_username',
        'statusid',
        'created_name',
        'updated_name',
        'updated_at',
        'created_at'
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
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
