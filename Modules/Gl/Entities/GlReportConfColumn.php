<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class GlReportConfColumn extends Model
{
    use HasFactory;

    protected $table = 'gl_report_conf_column';

    protected $fillable = [
        'id',
        'conf_detail_id',
        'columnidx',
        'acntno',
        'multiply',
        'isbegbal',
        'istranbal',
        'type',
        'statusid',
        'instid',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'multiply' => 'double',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];

    public function conttxns()
    {
        return $this->hasMany(GlReportConfColumnContTxn::class, 'conf_column_id', 'id')
            ->where('statusid', 1);
    }
}
