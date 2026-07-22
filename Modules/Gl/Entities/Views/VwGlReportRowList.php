<?php

namespace Modules\Gl\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGlReportRowList extends Model
{
    use HasFactory;

    protected $table = 'vw_gl_report_row_list';

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

     protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
