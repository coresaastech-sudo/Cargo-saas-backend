<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstAddField extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_add_field';

    protected $fillable = [
        'id',
        'typecode',
        'name',
        'name2',
        'tagtype',
        'taglen',
        'tagmask',
        'mandatory',
        'descr',
        'listorder',
        'defaultvalue',
        'readonly',
        'minvalue',
        'maxvalue',
        'code',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'code'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'minvalue' => 'double',
        'maxvalue' => 'double',
    ];
}
