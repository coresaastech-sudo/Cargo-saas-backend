<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdZmsInquiry extends Model
{
    use HasFactory;

    protected $table = 'ad_zms_inquiry';

    protected $fillable = [
        'id',
        'productno',
        'productname',
        'purptypeid',
        'acnttypeid',
        'custtypeid',
        'custregno',
        'origin',
        'price',
        'fee',
        'fee_acntno',
        'pdf',
        'instid',
        'stmt_id',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
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
