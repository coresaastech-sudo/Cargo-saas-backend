<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpProviderConf extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_provider_conf';

    protected $fillable = [
        'id',
        'code',
        'name',
        'name2',
        'connid',
        'typeid',
        'config',
        'descr',
        'sec1',
        'sec2',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
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
