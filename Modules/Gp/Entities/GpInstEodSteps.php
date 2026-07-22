<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstEodSteps extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_eod_steps';

    protected $fillable = [
        'id',
        'orderno',
        'name',
        'name2',
        'stepdesc',
        'controller',
        'function',
        'exturl',
        'statusid',
        'runfreq',
        'modifyopt',
        'proctype',
        'sqlscript',
        'runmonth',
        'runday',
        'sendsms',
        'sendemail',
        'instid',
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
