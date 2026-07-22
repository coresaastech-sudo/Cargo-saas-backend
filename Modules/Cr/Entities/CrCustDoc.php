<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class CrCustDoc extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_doc';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'custid';
    protected $fillable = [
        'custid',
        'instid',
        'custtypecode',
        'file',
        'name',
        'name2',
        'filename',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $auditExclude = [
        'file',
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
