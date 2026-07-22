<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class DictionaryMain extends Model
{
    protected $table = 'gp_dic_mains';

    protected $fillable = [
        'dic_code',
        'vw_name',
        'description',
        'statusid',
        'created_by',
        'updated_by',
    ];
}
