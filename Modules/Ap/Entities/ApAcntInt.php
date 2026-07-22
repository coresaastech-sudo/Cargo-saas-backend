<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ApAcntInt extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'ap_acnt_int';

    public $fillable = [
        'acnt_code',
        'instid',
        'userid',
        'statusid',
        'created_at',
        'created_by',
        'other_info',
        'pay_cust_name',
        'int_rate',
        'source_bal_type',
        'last_acr_info',
        'type',
        'accr_int_amt',
        'int_type_name',
        'int_rate_option',
        'daily_int_amt',
        'last_acr_txn_seq',
        'bal_type_code',
        'int_type_code',
        'last_acr_amt',
        'last_accrual_date',
        'int_lvl',
        'int_lvl_name',
    ];

    protected $casts = [
        'accr_int_amt' => 'double',
        'daily_int_amt' => 'double',
        'last_acr_amt' => 'double',
        'last_accrual_date' => 'date:Y-m-d H:i:s',
        'id' => 'int',
        'instid' => 'int',
        'int_rate' => 'double',
    ];
}
