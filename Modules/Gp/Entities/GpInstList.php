<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstList extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_list';

    protected $fillable = [
        'id',
        'name',
        'name2',
        'regno',
        'nationid',
        'stabledate',
        'inst_typeid',
        'license_typeid',
        'email',
        'phone',
        'dir_name',
        'dir_name2',
        'color',
        'logo',
        'state',
        'region',
        'subregion',
        'street',
        'zipcode',
        'w3w',
        'cbegno',
        'cendno',
        'cnextno',
        'acntbegno',
        'acntendno',
        'acntnextno',
        'iaacntbegno',
        'iaacntendno',
        'iaacntnextno',
        'appbegno',
        'appendno',
        'appnextno',
        'collbegno',
        'collendno',
        'collnextno',
        'deductionbegno',
        'deductionendno',
        'deductionnextno',
        'statusid',
        'billstartdate',
        'iscreate_invoice',
        'listorder',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'stabledate' => 'date:Y-m-d',
        'billstartdate' => 'date:Y-m-d',
    ];
}
