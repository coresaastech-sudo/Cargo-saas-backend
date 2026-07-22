<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdPermReport extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_perm_report';

    protected $fillable = [
        'id',
        'AC',
        'valuetype',
        'userid',
        'brchno',
        'showbrchno',
        'statusid',
        'instid',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];
    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
