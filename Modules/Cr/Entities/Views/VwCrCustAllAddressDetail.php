<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustAllAddressDetail extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_cust_all_addr_detail';
    protected $fillable = [
        'id',
        'custid',
        'custtypecode',
        'addrtypecode',
        'statusid',
        'state',
        'region',
        'subregion',
        'address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
}
