<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class ModuleDefinition extends Model
{
    protected $table = 'gp_modules';

    protected $primaryKey = 'module_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'module_code',
        'name',
        'description',
        'sort_order',
        'status',
    ];
}
