<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstBrch extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_brch';
    protected $fillable = [
        'brchno',
        'name',
        'name2',
        'dirname',
        'dirname2',
        'begindate',
        'phone',
        'fax',
        'email',
        'isonline',
        'bankcode',
        'blevel',
        'biccode',
        'doestrade',
        'listorder',
        'state',
        'region',
        'subregion',
        'address',
        'zipcode',
        'w3w',
        'instid',
        'statusid',
        'taxregion',
        'taxsubregion',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'state_name',
        'region_name',
        'subregion_name',
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
