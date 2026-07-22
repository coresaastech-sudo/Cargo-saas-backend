<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class ApInstStopService extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ap_inst_stop_service';

    protected $fillable = [
        'instid',
        'name',
        'prod_code',
        'prod_type',
        'operation',
        'description',
        'begin_date',
        'end_date',
        'created_by',
        'updated_by',
        'statusid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'begin_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

}
