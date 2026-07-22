<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class VwCrCustAdd extends Model
{
    use HasFactory, Auditable;

    protected $table = 'vw_cr_cust_add';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'custid';

    protected $fillable = [
        'id',
        'keyfield',
        'itemvalue',
        'statusid',
        'custtypecode',
        'custid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'code',
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
