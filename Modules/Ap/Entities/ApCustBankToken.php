<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApCustBankToken extends Model
{
    use HasFactory;

    protected $table = 'ap_cust_bank_token';

    protected $fillable = [
        'id',
        'cust_user_id',
        'customerregisterid',
        'tokenid',
        'maskedpan',
        'expdate',
        'statusid',
        'brand',
        'bankname',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'cust_user_id' => 'integer',
        'statusid' => 'integer',
        'created_at' => 'date:Y-m-d H:i:s',
        'created_by' => 'integer',
    ];

    protected $hidden = [
        'cust_user_id',
        'statusid',
    ];
}
