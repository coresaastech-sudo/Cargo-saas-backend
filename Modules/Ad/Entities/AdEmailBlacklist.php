<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdEmailBlacklist extends Model
{
    use HasFactory;

    protected $table = 'ad_email_blacklist';

    protected $fillable = [
        'emailaddress',
        'lastupdatetime',
        'reason',
        'desc',
        'source',
        'process',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'lastupdatetime' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
