<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class ApCustomer extends Model
{
    use HasFactory;

    protected $table = 'ap_cust';

    protected $fillable = [
        'instid',
        'corrid',
        'cif',
        'familyname',
        'familyname2',
        'lname',
        'lname2',
        'fname',
        'fname2',
        'gender',
        'regno',
        'nationality',
        'birthday',
        'lang',
        'ethnicity',
        'citizenship',
        'birthplace',
        'segment',
        'employment',
        'categories',
        'education',
        'maritalstatus',
        'phone',
        'phone2',
        'email',
        'fax',
        'familysize',
        'region',
        'subregion',
        'address',
        'industry',
        'shortname',
        'shortname2',
        'isbl',
        'iscompanycustomer',
        'ispolitical',
        'isvatpayer',
        'monthlyincome',
        'immovabletype',
        'ownership',
        'register_mask_code',
        'custtypecode',
        'statusid',
        'created_by',
        'updated_by',
    ];
    protected $appends = ['statusname'];
    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
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
