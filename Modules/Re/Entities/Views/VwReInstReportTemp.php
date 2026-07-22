<?php

namespace Modules\Re\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwReInstReportTemp extends Model
{
    use HasFactory;
    protected $table = 'vw_re_inst_report_temp';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'name2',
        'dimensionid',
        'orientation',
        'pagemargin',
        'ACTION_CODE',
        'hasheader',
        'headersize',
        'headerrepeat',
        'hasfooter',
        'footersize',
        'footerrepeat',
        'contentheight',
        'exporttype',
        'module',
        'groupid',
        'font',
        'instid',
        'statusid',
        'groupid_name',
        'groupid_name2',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
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
