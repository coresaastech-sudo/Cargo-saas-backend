<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstFeeTypeRate extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_fee_rate';

    protected $fillable = [
        'id',
        'feecode',
        'curcode',
        'intervalno',
        'perrate',
        'flatrate',
        'minamount',
        'maxamount',
        'calcmeth',
        'uselncount',
        'loancount',
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
        'flatrate' => 'float',
        'perrate' => 'float',
        'minamount' => 'float',
        'maxamount' => 'float',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
