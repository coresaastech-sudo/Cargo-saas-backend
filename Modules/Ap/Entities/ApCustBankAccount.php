<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApCustBankAccount extends Model
{
    use HasFactory;

    protected $table = 'ap_cust_bank_account';

    protected $fillable = [
        'cust_user_id',
        'acnt_code',
        'acnt_name',
        'token',
        'confirmed_at',
        'bank_code',
        'statusid',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'cust_user_id' => 'integer',
        'statusid' => 'integer',
        'confirmed_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'created_by' => 'integer',
    ];

    protected $hidden = [
        'token',
        'confirmed_at',
        'cust_user_id',
        'statusid',
    ];
}
