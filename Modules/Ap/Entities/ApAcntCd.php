<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApAcntCd extends Model
{
    use HasFactory;

    protected $table = 'ap_acnt_cd';

    protected $fillable = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'avail_balance' => 'float',
        'total_exp_amount' => 'float',
        'total_limit' => 'float',
        'min_pay_amt' => 'float'
    ];

}
