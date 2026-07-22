<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\AcntStatusCodeEnum;
use Modules\Gp\Enums\LnStatusCodeEnum;
use Illuminate\Support\Str;

class VwCrCustAllAcntWithBalance extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_cust_all_acnt_with_balance';
    protected $fillable = [
        'custid',
        'custno',
        'custname',
        'custname2',
        'custtypecode',
        'acntno',
        'acntmode',
        'statusid',
        'brchno',
        'prodcode',
        'name',
        'name2',
        'curcode',
        'curcode_name',
        'clscode',
        'instid',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        if (Str::upper($this->acntmode) == 'LN') {
            return __('messages.' . LnStatusCodeEnum::toString($this->statusid));
        } else {
            return __('messages.' . AcntStatusCodeEnum::toString($this->statusid));
        }
    }
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
