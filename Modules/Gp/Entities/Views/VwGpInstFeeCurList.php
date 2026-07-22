<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstFeeCurList extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_fee_cur_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
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
        'created_at',
        'updated_at',
        'created_name',
        'updated_name'
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'flatrate' => 'float',
        'perrate' => 'float',
        'minfee' => 'float',
        'maxfee' => 'float',
        'vat_split_percent' => 'float',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
