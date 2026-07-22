<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstTxnType extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_txn_type';
    protected $primaryKey = 'ACTION_CODE';

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('ACTION_CODE', '=', $this->getAttribute('ACTION_CODE'))
            ->where('statusid', '=', $this->getAttribute('statusid'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

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

    protected $casts = [
        'id' => 'string',
        'txnopt' => 'integer',
        "ACTION_CODE" => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'qualifier' => 'integer',
        'crintmethod' => 'integer',
    ];
}
