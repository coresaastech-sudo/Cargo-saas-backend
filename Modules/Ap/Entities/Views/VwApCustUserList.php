<?php

namespace Modules\Ap\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwApCustUserList extends Model
{
    use HasFactory;
    protected $table = 'vw_ap_inst_cust_user_list';
    protected $fillable = [
        'instid',
        'regno',
        'lastname',
        'firstname',
        'email',
        'statusid',
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
