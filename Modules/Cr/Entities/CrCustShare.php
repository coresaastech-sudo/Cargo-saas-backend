<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class CrCustShare extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_shareholder';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'custid';
    protected $fillable = [
        'id',
        'custid',
        'custtypecode',
        'custid2',
        'custid2typecode',
        'sharetypecode',
        'sharepercent',
        'begindate',
        'desc',
        'brchno',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
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
