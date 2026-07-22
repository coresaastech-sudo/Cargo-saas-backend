<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;
use Modules\Gp\Traits\Auditable;

class CrCustOrg extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_org';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'id';

    protected $fillable = [
        'id',
        'custno',
        'custtypecode',
        'image',
        'id1',
        'id1typecode',
        'id2typecode',
        'id2',
        'name',
        'name2',
        'segcode',
        'dirname',
        'dirname2',
        'dirlname',
        'dirlname2',
        'diridcode',
        'dirid',
        'diridcode2',
        'dirid2',
        'contactpname',
        'contactppos',
        'contactpphone',
        'orgtypecode',
        'inducode',
        'indusubcode',
        'countrycode',
        'workphone',
        'email',
        'email_verified',
        'catcode',
        'birthdate',
        'lastrenewdate',
        'brchno',
        'created_name',
        'updated_name',
        'empcount',
        'statusid',
        'prevstatusid',
        'lasttxndate',
        'card',
        'ispolitical',
        'hidden',
        'bl',
        'loancount',
        'managerno',
        'manager_name',
        'sourcecode',
        'instid',
        'txndate',
        'partner_type',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'tin',
    ];
    protected $appends = ['statusname'];
    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'txndate' => 'date:Y-m-d',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }
}
