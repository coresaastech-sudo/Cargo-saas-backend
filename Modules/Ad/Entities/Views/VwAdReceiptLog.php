<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwAdReceiptLog extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_receipt_logs';

    protected $fillable = [];

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
