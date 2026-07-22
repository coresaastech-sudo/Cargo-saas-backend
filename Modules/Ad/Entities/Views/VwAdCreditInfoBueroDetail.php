<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdCreditInfoBueroDetail extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_credit_buero_detail';

    protected $fillable = [
        'id1',
        'name',
        'id',
        'buero_id',
        'custno',
        'acntno',
        'loancode',
        'status',
        'type',
        'action',
        'advamount',
        'starteddate',
        'expiredate',
        'curcode',
        'balance',
        'extdate',
        'interestinperc',
        'commissionperc',
        'sectorcode',
        'fee',
        'loanclasscode',
        'isapproved',
        'linetype',
        'loaninterest',
        'timestoloan',
        'loanprovenance',
        'loanintype',
        'receivabletype',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'loan_contract_no',
        'loan_contract_date',
        'loan_contract_change_reason',
        'loan_int_balance',
        'loan_additional_int_balance',
        'loan_additional_interest',
        'loan_paid_date',
        'loan_decide_status',
        'brchno_name',
        'brchno'
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        // 'updated_at' => 'datetime:Y-m-d H:i:s',
        // 'created_at' => 'datetime:Y-m-d H:i:s',
        'advamount' => 'float',
        'balance' => 'float',
        'fee' => 'float',
        'loan_additional_interest' => 'float',
    ];
}
