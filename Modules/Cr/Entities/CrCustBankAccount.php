<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrCustBankAccount extends Model
{
    use HasFactory;

    protected $table = 'cr_cust_bank_account';

    protected $fillable = [
        'custid',
        'custno',
        'acnt_code',
        'iban',
        'acnt_name',
        'bank_code',
        'confirmed_at',
        'statusid',
        'instid',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'confirmed_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];
}
