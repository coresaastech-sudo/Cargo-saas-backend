<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GlReportConfList extends Model
{
    use HasFactory, Auditable;

    protected $table = 'gl_report_conf_list';

    protected $fillable = [
        'id',
        'name',
        'name2',
        'statusid',
        'colcount',
        'AC',
        'listorder',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

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
