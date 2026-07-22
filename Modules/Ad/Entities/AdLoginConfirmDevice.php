<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdLoginConfirmDevice extends Model
{
    use HasFactory;

    protected $table = 'ad_login_confirm_device';

    protected $fillable = [
        'userid',
        'ip',
        'is_confirm',
        'token',
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
