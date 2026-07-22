<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdEbarimtConfig extends Model
{
    use HasFactory;

    protected $table = 'ad_ebarimt_config';

    protected $fillable = [
        'id',
        'pos_api_address',
        'pos_api_port',
        'vat_percentage',
        'registerno',
        'branchno',
        'posid',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
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
