<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstCurRate extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_cur_rate';


    protected $fillable = [
        'rtypecode',
        'curcode',
        'salerate',
        'buyrate',
        'listorder',
        'instid',
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
        'typecode'  => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
