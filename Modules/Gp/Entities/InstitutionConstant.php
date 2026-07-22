<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class InstitutionConstant extends Model
{
    protected $table = 'gp_inst_consts';

    protected $fillable = [
        'organization_id',
        'dic_code',
        'code',
        'name',
        'name2',
        'value',
        'parent_code',
        'listorder',
        'statusid',
        'created_by',
        'updated_by',
    ];
}
