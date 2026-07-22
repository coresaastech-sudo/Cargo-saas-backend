<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ApAcntStatement extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $fillable = [
        'cont_cur_rate',
        'income',
        'jrno',
        'begin_bal',
        'end_bal',
        'txn_date',
        'txn_code',
        'bal_type_code',
        'outcome',
        'balance',
        'txn_desc',
        'cont_acnt_code',
        'cont_bank_acnt_code',
        'cont_bank_acnt_name',
        'cont_bank_code',
        'cont_bank_name',
        'post_date',
        'id',
        'instid',
        'acnt_code',
    ];

    protected $casts = [
        'cont_cur_rate' => 'double',
        'income' => 'double',
        'jrno' => 'string',
        'begin_bal' => 'double',
        'end_bal' => 'double',
        'txn_date' => 'date:Y-m-d',
        'txn_code' => 'string',
        'bal_type_code' => 'string',
        'outcome' => 'double',
        'balance' => 'double',
        'txn_desc' => 'string',
        'cont_acnt_code' => 'string',
        'cont_bank_acnt_code' => 'string',
        'cont_bank_acnt_name' => 'string',
        'cont_bank_code' => 'string',
        'cont_bank_name' => 'string',
        'post_date' => 'date:Y-m-d H:i:s',
        'id' => 'int',
        'instid' => 'int',
    ];
}
