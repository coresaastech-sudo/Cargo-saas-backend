<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwGpInstTxnTypeList extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_txn_type_list';
    protected $primaryKey = 'ACTION_CODE';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "ACTION_CODE",
        "name",
        "name2",
        'txnopt',
        'qualifier',
        'acnttype1',
        'acntno1',
        'acnttype2',
        'acntno2',
        'moduleid',
        'txntype',
        'rtypecode',
        'isbatchfee',
        'batchfeetxncode',
        'batchfeetxndesc',
        'instid',
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
        'id' => 'string',
        'txnopt' => 'integer',
        "ACTION_CODE" => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
