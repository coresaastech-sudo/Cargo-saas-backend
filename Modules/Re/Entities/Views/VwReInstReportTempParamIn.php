<?php

namespace Modules\Re\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwReInstReportTempParamIn extends Model
{
    use HasFactory;
    protected $table = 'vw_re_inst_report_temp_param_in';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'templateid',
        'parameterid',
        'name',
        'name2',
        'input',
        'forminputtype',
        'listorder',
        'hasinputcondition',
        'inputid',
        'inputcondition',
        'dropdowndic',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
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
