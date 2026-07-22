<?php

namespace Modules\Ap\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwApCustBankToken extends Model
{
    use HasFactory;

    protected $table = 'vw_ap_cust_bank_token';

    protected $fillable = [
        'id',
        'cust_user_id',
        'customerregisterid',
        'tokenid',
        'maskedpan',
        'expdate',
        'brand',
        'bankname',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'bank_name',
        'bank_name2',
        'dicvalue1',
        'dicvalue2',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
    ];
}
