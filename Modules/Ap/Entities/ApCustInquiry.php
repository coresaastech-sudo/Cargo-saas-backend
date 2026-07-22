<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class ApCustInquiry extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ap_cust_inquiry';

    protected $fillable = [
        'id',
        'productno',
        'regno',
        'custtypeid',
        'purptypeid',
        'purposedesc',
        'pdf_url',
        'servicecode',
        'service_detail_date',
        'price',
        'inquiry',
        'userid',
        'statusid',
        'created_by',
        'updated_by',

    ];

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
