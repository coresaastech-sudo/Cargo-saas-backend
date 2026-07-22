<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class AdEbarimtActionCode extends Model
{
    use HasFactory;

    protected $table = 'ad_ebarimt_ACTION_CODE';

    protected $fillable = [
        'id',
        'ACTION_CODE',
        'parent_ACTION_CODE',
        'classification_code',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
