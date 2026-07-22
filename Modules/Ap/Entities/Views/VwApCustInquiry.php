<?php

namespace Modules\Ap\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwApCustInquiry extends Model
{
    use HasFactory;
    protected $table = 'vw_ap_cust_inquiry';
    protected $fillable = [
        'id',
        'productno',
        'regno',
        'custtypeid',
        'pdf_url',
        'servicecode',
        'service_detail_date',
        'price',
        'inquiry',
        'purptypeid',
        'purposedesc',
        'userid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'prod_name',
        'cust_name',
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