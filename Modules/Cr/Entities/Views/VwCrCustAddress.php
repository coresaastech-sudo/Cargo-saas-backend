<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustAddress extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_cust_address';
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
        'zipcode',
        'w3w',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'created_by_name',
        'updated_by_name',
        'state_name',
        'region_name',
        'subregion_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
}
