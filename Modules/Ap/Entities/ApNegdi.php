<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApNegdi extends Model
{
    use HasFactory;

    protected $table = 'ap_negdi';

    public $fillable = [
        'id',
        'ordertype',
        'terminalid',
        'username',
        'password',
        'returnurl',
        'amount',
        'cur_code',
        'customerid',
        'customername',
        'description',
        'checkid',
        'tranid',
        'status',
        'approvalCode',
        'tranActionId',
        'ridByPmo',
        'customerregisterid',
        'tokenid',
        'maskedpan',
        'expdate',
        'detail',
        'negdiurl',
        'callbacked_at',
        'jrno',
        'typeid',
        'to_account',
        'ordernum',
        'txn_type',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'tranid' => 'string',
        'created_at' => 'date:Y-m-d H:i:s',
        'created_by' => 'integer',
        'callbacked_at' => 'date:Y-m-d H:i:s',
        'jrno' => 'integer',
        'typeid' => 'string',
        'txn_type' => 'string',
        'to_account' => 'string',
    ];

    public static function getInvoiceRules()
    {
        return [
            'typeid' => 'required|string|max:10',
            'txn_type' => 'required|string|max:2',
            'to_account' => 'string|max:20',
            'amount' => 'numeric|required|min:10',
            'productno' => 'nullable',
            'purptypeid' => 'nullable',
            'purpdesc' => 'nullable',
            'acnttypeid' => 'nullable',
            'custregno' => 'nullable',
            'custphone' => 'nullable',
            'custemail' => 'nullable',
            'custtypeid' => 'nullable',
            'invoice_description' => 'nullable',
            'inquiry_id' => 'nullable',
            'inquiry_type' => 'nullable',
        ];
    }

    public static function getInvoiceMessages()
    {
        return [
            'typeid.required' => 'Type Id талбар хоосон байж болохгүй.'
        ];
    }
}
