<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstFeeTypeCur extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_fee_cur';

    protected $fillable = [
        'feecode',
        'curcode',
        'calcmeth',
        'perrate',
        'flatrate',
        'minfee',
        'maxfee',
        'feecurcode',
        'vat_split_percent',
        'vat_txncode',
        'formula',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'calcmeth' => 'integer',
        'flatrate' => 'float',
        'perrate' => 'float',
        'minfee' => 'float',
        'maxfee' => 'float',
        'vat_split_percent' => 'float',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
