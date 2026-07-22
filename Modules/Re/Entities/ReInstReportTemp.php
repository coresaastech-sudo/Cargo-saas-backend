<?php

namespace Modules\Re\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class ReInstReportTemp extends Model
{
    use HasFactory, Auditable;

    protected $table = 're_inst_report_temp';
    protected $primaryKey = 'id';

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
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'version',
        'isbackground',
        'code',
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
