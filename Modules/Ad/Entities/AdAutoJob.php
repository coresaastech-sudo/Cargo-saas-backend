<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdAutoJob extends Model
{
    use HasFactory;

    protected $table = 'ad_auto_job';

    protected $fillable = [
        'id',
        'name',
        'name2',
        'formulaid',
        'ACTION_CODE',
        'execfreq',
        'execday',
        'exectime',
        'job_type',
        'hastimelimit',
        'startdate',
        'enddate',
        'lastexecdate',
        'statusid',
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
