<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwAdZmsInquiryDetail extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_zms_inquiry_detail';

    protected $fillable = [];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'id',
        'productno',
        'productname',
        'purptypeid',
        'acnttypeid',
        'custtypeid',
        'custregno',
        'pdf',
        'origin',
        'created_by',
        'instid',
        'stmt_id',
        'instname',
        'statusid',
        'statusname',
        'created_at',
    ];
}
