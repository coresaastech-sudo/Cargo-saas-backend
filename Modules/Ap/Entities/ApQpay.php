<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApQpay extends Model
{
    use HasFactory;

    protected $table = 'ap_qpay';

    protected $fillable = [
        'id',
        'sender_invoice_no',
        'invoice_receiver_code',
        'invoice_description',
        'amount',
        'cur_code',
        'callback_url',
        'invoice_id',
        'qr_text',
        'qpay_shorturl',
        'callbacked_at',
        'checked_paid_amount',
        'checked_count',
        'checked_rows',
        'checked_date',
        'jrno',
        'typeid',
        'to_account',
        'txn_type',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'inquiry_id'
    ];

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
