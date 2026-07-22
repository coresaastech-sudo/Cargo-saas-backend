<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;
use Modules\Gp\Traits\Auditable;

class CrCustInd extends Model
{
    use HasFactory, Auditable;

    protected $table = 'cr_cust_ind';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'string';
    public $parentObjectField = 'id';

    protected $fillable = [
        'id',
        'custno',
        'custtypecode',
        'image',
        'id1',
        'id2',
        'id1typecode',
        'id2typecode',
        'familyname',
        'familyname2',
        'name',
        'name2',
        'lname',
        'lname2',
        'sexcode',
        'birthdate',
        'segcode',
        'inducode',
        'indusubcode',
        'catcode',
        'handphone',
        'email',
        'email_verified',
        'titlecode',
        'langcode',
        'nationcode',
        'educode',
        'profession',
        'countrycode',
        'maritalstatuscode',
        'familymembercount',
        'statusid',
        'sourcecode',
        'instid',
        'txndate',
        'lasttxndate',
        'brchno',
        'created_name',
        'updated_name',
        'ispolitical',
        'prevstatusid',
        'workplace',
        'position',
        'card',
        'lastrenewdate',
        'managerno',
        'manager_name',
        'hidden',
        'bl',
        'loancount',
        'partner_type',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
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
