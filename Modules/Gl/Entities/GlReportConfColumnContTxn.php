<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlReportConfColumnContTxn extends Model
{
    use HasFactory;

    protected $table = 'gl_report_conf_column_cont_txn';

    protected $fillable = [
        'conf_column_id',
        'contacntno',
        'conttrantype',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
