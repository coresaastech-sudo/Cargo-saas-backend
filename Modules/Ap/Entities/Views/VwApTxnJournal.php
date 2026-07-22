<?php

namespace Modules\Ap\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwApTxnJournal extends Model
{
    use HasFactory;

    protected $table = 'vw_ap_txn_journal';

    protected $fillable = [
        'instid',
        'inst_name',
        'userid',
        'txn_acnt_code',
        'txn_date',
        'txn_amount',
        'cur_code',
        'txn_jrno',
        'txn_corr_jrno',
        'txn_desc',
        'txn_type',
        'identity_type',
        'cont_acnt_code',
        'cont_amount',
        'cont_bank_code',
        'cont_cur_code',
        'cont_rate',
        'core_corr_jrno',
        'core_jrno',
        'err_desc',
        'fee_id',
        'fee_inst_amount',
        'fee_sys_amount',
        'internal_cont_acnt_code',
        'is_preview',
        'is_preview_fee',
        'is_supervisor',
        'is_tmw',
        'jr_item_no_and_incr',
        'oper_code',
        'parent_jrno',
        'rate',
        'source_type',
        'tcust_addr',
        'tcust_contact',
        'tcust_name',
        'tcust_register',
        'tcust_register_mask',
        'statusid',
        'created_by',
        'updated_by',
        'prodcode'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'txn_amount' => 'float',
        'cont_amount' => 'float',
        'cont_rate' => 'float',
        'fee_inst_amount' => 'float',
        'fee_sys_amount' => 'float',
        'rate' => 'float',
    ];
}
