<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwAdEbarimt extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_ebarimt';

    protected $fillable = [
        'id',
        'jrno',
        'moduleid',
        'txndate',
        'customerno',
        'amount',
        'vat',
        'cashAmount',
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
        'prev_id',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'pc_name'
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
    ];
}
