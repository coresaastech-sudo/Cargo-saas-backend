<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdEbarimt extends Model
{
    use HasFactory;

    protected $table = 'ad_ebarimt';

    protected $fillable = [
        'id',
        'jrno',
        'moduleid',
        'txndate',
        'customerno',
        'amount',
        'vat',
        'cashamount',
        'noncashamount',
        'billtype',
        'taxtype',
        'txncode',
        'curcode',

        'res_billid',
        'res_qrdata',
        'res_internalcode',
        'res_date',
        'res_lottery',
        'res_lotterywarningmsg',
        'res_success',
        'res_errorcode',
        'res_message',
        'res_warningmsg',

        'acntno',
        'refused_amount',
        'prev_id',

        'instid',
        'statusid',
        'ebarimt_consumerno',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public $response_fields = [
        'res_billId',
        'res_qrData',
        'res_internalCode',
        'res_date',
        'res_lottery',
        'res_lotteryWarningMsg',
        'res_success',
        'res_errorcode',
        'res_message',
        'res_warningmsg'
    ];

    protected $hidden = [
        'res_lottery',
        'res_qrdata',
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',

        'amount' => 'float',
        'cashamount' => 'float',
        'noncashamount' => 'float',
        'vat' => 'float',
        'res_success' => 'int'
    ];
}
