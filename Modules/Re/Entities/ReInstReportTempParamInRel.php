<?php

namespace Modules\Re\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class ReInstReportTempParamInRel extends Model
{
    use HasFactory, Auditable;

    protected $table = 're_inst_report_temp_param_in_rel';

    protected $fillable = [
        'id',
        'templateid',
        'paramid',
        'inputid',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

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
