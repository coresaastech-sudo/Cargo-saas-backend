<?php

namespace Modules\Gl\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGlReportConfList extends Model
{
    use HasFactory;

    protected $table = 'vw_gl_report_conf_list';

    protected $fillable = [
        'name',
        'name2',
        'statusid',
        'colcount',
        'listorder',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $auditExclude = [
        'acntno',
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
