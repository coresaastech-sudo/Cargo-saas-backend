<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdCreditInfoBueroDetail extends Model
{
    use HasFactory;

    protected $table = 'ad_credit_info_buero_detail';

    protected $fillable = [
        'id',
        'buero_id',
        'acntno',
        'custno',
        'loancode',
        'status',
        'type',
        'action',
        'timestoloan',
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
        'loan_contract_date',
        'loan_contract_no',
        'loan_contract_change_reason',
        'loan_int_balance',
        'loan_additional_int_balance',

        'linetype',
        'loaninterest',
        'loan_additional_interest',
        'timestoloan',

        'provideloansize',
        'loanprovenance',
        'loanintype',
        'loan_paid_date',
        'loan_decide_status',

        'receivabletype',

        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'advamount' => 'double',
        'balance' => 'double',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
