<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class CrCustSignAcnt extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_sign_acnt';

    protected $fillable = [
        'custid',
        'signid',
        'sign_level',
        'acnt_module',
        'acntno',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];

}
