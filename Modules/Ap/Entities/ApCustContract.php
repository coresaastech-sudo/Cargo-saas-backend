<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApCustContract extends Model
{
    use HasFactory;

    protected $table = 'ap_cust_contracts';

    protected $fillable = [
        'id',
        'instid',
        'cust_cif',
        'cust_name',
        'operation',
        'account_no',
        'prod_code',
        'txn_jrno',
        'contract',
        'sign_image_id',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
