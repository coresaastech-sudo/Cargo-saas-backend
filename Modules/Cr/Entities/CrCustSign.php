<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class CrCustSign extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_sign';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'custid';
    protected $fillable = [
        'id',
        'custid',
        'instid',
        'custtypecode',
        'image',
        'name',
        'name2',
        'sign_level',
        'created_by',
        'updated_by',
        'statusid',
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
