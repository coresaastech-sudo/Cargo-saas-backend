<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApCustIncome extends Model
{
    use HasFactory;

    protected $table = 'ap_cust_income';

    protected $fillable = [
        'instid',
        'regno',
        'cif',
        'cust_userid',
        'type',
        'source_name',
        'year',
        'month',
        'amount',
        'fee',
        'net_income',
        'statusid',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'net_income' => 'decimal:8',
    ];
}