<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlReportConfRowList extends Model
{
    use HasFactory;

    protected $table = 'gl_report_conf_detail';

    protected $fillable = [
        'report_conf_id',
        'num',
        'name',
        'name2',
        'isbegbal',
        'isbold',
        'listorder',
        'statusid',
        'instid',
        'created_by',
        'updated_by'
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */

    // protected $casts = [
    //     'updated_at' => 'date:Y-m-d H:i:s',
    //     'created_at' => 'date:Y-m-d H:i:s',
    // ];
}
